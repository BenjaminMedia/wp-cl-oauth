// Polyfill for 'findIndex' because Internet Explorer.
// See: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Array/findIndex
if (!Array.prototype.findIndex) {
    Object.defineProperty(Array.prototype, 'findIndex', {
        value: function(predicate) {
            // 1. Let O be ? ToObject(this value).
            if (this == null) {
                throw new TypeError('"this" is null or not defined');
            }

            var o = Object(this);

            // 2. Let len be ? ToLength(? Get(O, "length")).
            var len = o.length >>> 0;

            // 3. If IsCallable(predicate) is false, throw a TypeError exception.
            if (typeof predicate !== 'function') {
                throw new TypeError('predicate must be a function');
            }

            // 4. If thisArg was supplied, let T be thisArg; else let T be undefined.
            var thisArg = arguments[1];

            // 5. Let k be 0.
            var k = 0;

            // 6. Repeat, while k < len
            while (k < len) {
                // a. Let Pk be ! ToString(k).
                // b. Let kValue be ? Get(O, Pk).
                // c. Let testResult be ToBoolean(? Call(predicate, T, « kValue, k, O »)).
                // d. If testResult is true, return k.
                var kValue = o[k];
                if (predicate.call(thisArg, kValue, k, o)) {
                    return k;
                }
                // e. Increase k by 1.
                k++;
            }

            // 7. Return -1.
            return -1;
        }
    });
}

function bp_cl_oauth_trigger_login(redirectOnComplete) {

    const loginUri = '/wp-json/bp-oauth/oauth/login';

    if (typeof redirectOnComplete === 'undefined') {
        redirectOnComplete = document.location.href;
    }
    window.location = loginUri + '?redirect_uri=' + encodeURIComponent(redirectOnComplete);
}

function bp_cl_oauth_trigger_logout(redirectOnComplete) {

    const loginUri = '/wp-json/bp-oauth/oauth/logout';

    if (typeof redirectOnComplete === 'undefined') {
        redirectOnComplete = document.location.href;
    }

    window.location = loginUri + '?redirect_uri=' + encodeURIComponent(redirectOnComplete);
}

function getLoginUrl()
{
    return '/wp-json/bp-oauth/oauth/login?redirect_uri=' + encodeURIComponent(document.location.href);
}

function getLogoutUrl()
{
    return '/wp-json/bp-oauth/oauth/logout?redirect_uri=' + encodeURIComponent(document.location.href);
}

window.addEventListener('click', function (event) {

    var loginTriggerClass = 'bp-cl-oauth-login';
    var logoutTriggerClass = 'bp-cl-oauth-logout';

    if (event.target.className.indexOf(loginTriggerClass) > -1 || event.target.parentElement.className.indexOf(loginTriggerClass) > -1) {
        if (typeof event.target.dataset.bpClOauthRedirect !== 'undefined') {
            bp_cl_oauth_trigger_login(event.target.dataset.bpClOauthRedirect);
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

function getCookie(cname) {
    var name = cname + "=";
    var decodedCookie = decodeURIComponent(document.cookie);
    var ca = decodedCookie.split(';');
    for(var i = 0; i <ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
        }
    }
    return "";
}

var loggedIn = getCookie('bp_oauth_token');
var loginBtn = document.getElementById('user-navigation-btn');
var mobileLoginBtn = document.getElementById('user-mobile-navigation-btn');

if (loggedIn) {
    document.getElementById('user-navigation-btn-username').innerHTML = getCookie('bp_oauth_username');
    loginBtn.setAttribute('href', loginBtn.getAttribute('data-profile'));
    mobileLoginBtn.setAttribute('href', getLogoutUrl());
    document.getElementById('user-mobile-navigation-label').innerHTML = 'Logout';
} else {
    loginBtn.setAttribute('href', getLoginUrl());
    mobileLoginBtn.setAttribute('href', getLoginUrl());
}
