function bp_cl_oauth_trigger_login(redirectOnComplete, state) {

    const loginUri = '/wp-json/bp-cl-oauth/v1/oauth/login';

    if (typeof redirectOnComplete === 'undefined') {
        redirectOnComplete = document.location.href;
    }
    if (typeof state === 'undefined') {
        window.location = loginUri + '?redirectUri=' + encodeURIComponent(redirectOnComplete);
    }
    window.location = loginUri + '?redirectUri=' + encodeURIComponent(redirectOnComplete) + '&state=' + encodeURIComponent(state);
}

function bp_cl_oauth_trigger_logout(redirectOnComplete) {

    const loginUri = '/wp-json/bp-cl-oauth/v1/oauth/logout';

    if (typeof redirectOnComplete === 'undefined') {
        redirectOnComplete = document.location.href;
    }

    window.location = loginUri + '?redirectUri=' + encodeURIComponent(redirectOnComplete);
}

window.addEventListener('click', function (event) {

    var loginTriggerClass = 'bp-cl-oauth-login';
    var logoutTriggerClass = 'bp-cl-oauth-logout';

    if (event.target.className.indexOf(loginTriggerClass) > -1 || event.target.parentElement.className.indexOf(loginTriggerClass) > -1) {
        if (typeof event.target.dataset.bpClOauthRedirect !== 'undefined') {
            bp_cl_oauth_trigger_login(event.target.dataset.bpClOauthRedirect, event.target.dataset.bpClOauthState);
        }
        else {
            bp_cl_oauth_trigger_login();
        }
    }

    if (event.target.className.indexOf(logoutTriggerClass) > -1 || event.target.parentElement.className.indexOf(logoutTriggerClass) > -1) {
        if (typeof event.target.dataset.bpClOauthRedirect !== 'undefined') {
            bp_cl_oauth_trigger_logout(event.target.dataset.bpClOauthRedirect);
        } else {
            bp_cl_oauth_trigger_logout();
        }
    }
});