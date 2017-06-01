function bp_wa_oauth_trigger_login(redirectOnComplete) {

    const loginUri = '/wp-json/bp-wa-oauth/v1/oauth/login';

    if (typeof redirectOnComplete === 'undefined') {
        redirectOnComplete = document.location.href;
    }

    window.location = loginUri + '?redirectUri=' + encodeURIComponent(redirectOnComplete);
}

function bp_wa_oauth_trigger_logout(redirectOnComplete) {

    const loginUri = '/wp-json/bp-wa-oauth/v1/oauth/logout';

    if (typeof redirectOnComplete === 'undefined') {
        redirectOnComplete = document.location.href;
    }

    window.location = loginUri + '?redirectUri=' + encodeURIComponent(redirectOnComplete);
}

window.addEventListener('click', function (event) {

    var loginTriggerClass = 'bp-wa-oauth-login';
    var logoutTriggerClass = 'bp-wa-oauth-logout';

    if (event.target.className.indexOf(loginTriggerClass) > -1 || event.target.parentElement.className.indexOf(loginTriggerClass) > -1) {
        if (typeof event.target.dataset.bpClOauthRedirect !== 'undefined') {
            bp_wa_oauth_trigger_login(event.target.dataset.bpClOauthRedirect);
        } else {
            bp_wa_oauth_trigger_login();
        }
    }

    if (event.target.className.indexOf(logoutTriggerClass) > -1 || event.target.parentElement.className.indexOf(logoutTriggerClass) > -1) {
        if (typeof event.target.dataset.bpClOauthRedirect !== 'undefined') {
            bp_wa_oauth_trigger_logout(event.target.dataset.bpClOauthRedirect);
        } else {
            bp_wa_oauth_trigger_logout();
        }
    }
});