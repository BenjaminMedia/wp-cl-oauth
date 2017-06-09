<?php

namespace Bonnier\WP\ClOauth\Models;

use Bonnier\WP\ClOauth\Settings\SettingsPage;
use WP_User;

class User
{
    const ACCESS_TOKEN_META_KEY = 'bp_cl_oauth_access_token';
    const ON_USER_UPDATE_HOOK = 'bp_cl_oauth_on_user_update';

    public static function get_local_user_id($waId) {
        global $wpdb;
        return $wpdb->get_var(
            $wpdb->prepare("SELECT user_id FROM wp_usermeta WHERE meta_key=%s AND meta_value=%d", 'cl_user_id', $waId)
        );
    }

    public static function get_user_id_from_email($email) {
        global $wpdb;
        return $wpdb->get_var(
            $wpdb->prepare("SELECT ID FROM wp_users WHERE user_email='%s'", $email)
        );
    }

    public static function update_user_nicename($userId, $nicename) {
        global $wpdb;
        return $wpdb->update('wp_users', ['user_nicename' => $nicename], ['ID' => $userId], ['%s'], ['%d']);
    }

    public static function update_user_login($userObject, $new_login) {
        if($existingUser = get_user_by('login', $new_login)){    
            if($existingUser->ID === $userObject->ID) {
                return true;  
            } else {
                $new_login = $new_login . '-2';
            }
        }
        global $wpdb;
        return $wpdb->update('wp_users', ['user_login' => $new_login], ['ID' => $userObject->ID], ['%s'], ['%d']);
    }

    public static function get_access_token($userId) {
        return get_user_meta($userId, self::ACCESS_TOKEN_META_KEY, true);
    }

    public static function set_access_token($userId, $value) {
        return update_user_meta($userId, self::ACCESS_TOKEN_META_KEY, $value);
    }

    public static function create_local_user($commonLoginUser, $accessToken) {

        $localUser = static::get_local_user($commonLoginUser);

        $localUser = self::set_user_props($localUser, $commonLoginUser);

        $userId = wp_insert_user($localUser);

        // We have to update the user nicename because wp appends -2 when we call wp_insert_user
        self::update_user_nicename($userId, $commonLoginUser->username);

        self::set_access_token($userId, $accessToken);

        update_user_meta($userId, 'cl_user_id', $commonLoginUser->id);

        self::update_local_user($userId, $commonLoginUser);
    }

    /**
     * @param $commonLoginUser
     *
     * @return WP_User|null
     */
    public static function get_local_user($commonLoginUser) {
        $localUser = null;
        $localUser = new WP_User(self::get_local_user_id($commonLoginUser['id']));
        if(!isset($localUser) || !$localUser->exists()) { // check if user can be found by email
            if(isset($commonLoginUser['email'])) {
                $localUser = new WP_User(self::get_user_id_from_email($commonLoginUser['id']));
            }
        }
        return $localUser ?: null;
    }

    public static function wp_login_user($user) {
        if(isset($user->ID)) {
            wp_set_current_user($user->ID, $user->user_login);
            wp_set_auth_cookie($user->ID);
            do_action( 'wp_login', $user->user_login );
        }
        return false;
    }

    public static function update_local_user($localUserId, $commonLoginUser)
    {
        $localUser = new WP_User($localUserId);

        if($localUser->exists()) {

            $localUser = self::set_user_props($localUser, $commonLoginUser);

            // if a user's login is not already found in the database, we'll update the current one to the one in the $localUser object.
            $updated = wp_update_user($localUser) && self::update_user_login($localUser, $localUser->user_login);

            return ! is_wp_error($updated);
        }
        return false;
    }

    private static function set_user_props(WP_User $localUser, $commonLoginUser) {
        $localUser->user_login = sanitize_user($commonLoginUser->username);
        $localUser->first_name = $commonLoginUser->first_name;
        $localUser->last_name = $commonLoginUser->last_name;
        $localUser->user_nicename = $commonLoginUser->username;
        $localUser->display_name = $commonLoginUser->username;
        $localUser->nickname = $commonLoginUser->username;
        $localUser->user_url = $commonLoginUser->url;
        $localUser->user_email = $commonLoginUser->email;

        /*foreach($localUser as $property => $value){
            if(!property_exists($localUser, $property)){
                return;
            }
            dd($localUser);
        }*/

        // Password is required when creating a new user
        if(! $localUser->exists()) {
            $localUser->user_pass = md5($commonLoginUser->username . time());
        }

        $localUser = self::set_user_roles($localUser, $commonLoginUser->roles);

        /*
         * this filter is for if you want to insert data into the description field,
         * and/or other fields which has not been set by the WA user above.
         * If these fields are not set, WP will automatically set them to an empty string, every time the user logs in.
         */
        $localUser = apply_filters(self::ON_USER_UPDATE_HOOK, [
            'wp' => $localUser,
            'cl' => $commonLoginUser
        ]);

        if ( $localUser instanceof WP_User ) {
            return $localUser;
        }
        else{
            return $localUser['wp'];
        }
    }

    private static function set_user_roles($localUser, $roles)
    {
        foreach ($roles as $role) {
            $localUser->set_role(SettingsPage::ROLES_PREFIX . $role);
        }
        return $localUser;
    }
}
