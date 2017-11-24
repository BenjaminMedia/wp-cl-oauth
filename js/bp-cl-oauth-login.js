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

function getLoginUrl()
{
    return '/wp-json/bp-cl-oauth/v1/oauth/login?redirectUri=' + encodeURIComponent(document.location.href);
}

function getLogoutUrl()
{
    return '/wp-json/bp-cl-oauth/v1/oauth/logout?redirectUri=' + encodeURIComponent(document.location.href);
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

function setDownloadUrl(downloadBtns, response)
{
    if(!downloadBtns.length) { return; }

    Array.prototype.forEach.call(response, function(btn, i) {
        var newDownloadButton = document.querySelectorAll('[data-id="'+btn['data-id']+'"]');
        //Get all buttons matched by response
        Array.prototype.forEach.call(newDownloadButton, function(btnAfterResponse, j) {
            if(btn['data-disclaimer'] === "1")
            {
                btnAfterResponse.setAttribute('data-target', '#'+btn['data-target']+'-disclaimer');
                var allDisclaimerBtns = document.getElementById(btn['data-target']+'-disclaimer-download');
                allDisclaimerBtns.setAttribute('href', btn['data-response']);
            }
            else
            {
                var btnType = btnAfterResponse.getAttribute('data-type');
                if( btnType == 'video'){
                    videoModalTarget = btnAfterResponse.getAttribute('data-target');
                    btnAfterResponse.setAttribute('data-target',  videoModalTarget +'-video');
                    var videoModals = document.getElementById(videoModalTarget.replace(/^#/, '')+'-video');
                    //set the Iframe src attribute
                    var videoIframe = videoModals.querySelector('iframe');
                    videoIframe.setAttribute('src', btn['data-response']);
                    //set the caption
                    var videoCaption = videoModals.querySelector('div.caption');
                    videoCaption.innerHTML = btn['data-caption'];
                }
                else {
                    btnAfterResponse.setAttribute('href', btn['data-response']);
                    btnAfterResponse.setAttribute('target', '_blank');
                    btnAfterResponse.removeAttribute('data-toggle');
                }
            }
            btnAfterResponse.removeAttribute('disabled');
        });
    });
}

function setPaywall(downloadBtns) {
    if(!downloadBtns.length) { return; }
    Array.prototype.forEach.call(downloadBtns, function(el, i) {
        el.setAttribute('data-toggle', 'modal');
        el.removeAttribute('disabled');
    });
}

function checkAccess(downloadBtns)
{
    var uid = downloadBtns[0].getAttribute('data-uid');
    var pid = document.querySelectorAll('[data-content-id]');
    pid = pid[0].getAttribute('data-content-id');
    var downloadBtnsIds = [];

    Array.prototype.forEach.call(downloadBtns, function(el, i) {
        downloadBtnsIds[i] = {};
        downloadBtnsIds[i]['data-id'] = el.getAttribute('data-id');
        downloadBtnsIds[i]['data-index'] = el.getAttribute('data-index');
        downloadBtnsIds[i]['data-type'] = el.getAttribute('data-type');
        if(el.getAttribute('data-type') === 'file')
        {
            downloadBtnsIds[i]['data-disclaimer'] = el.getAttribute('data-disclaimer');
            //Remove hashtag
            downloadBtnsIds[i]['data-target'] = el.getAttribute('data-target');
            downloadBtnsIds[i]['data-target'] = downloadBtnsIds[i]['data-target'].replace(/^#/, '');
        }
    });

    var uniqueDownloadBtns = downloadBtnsIds.filter(function(btn, index, self) {
        return index === self.findIndex(function(t) {
                return t['data-id'] === btn['data-id']
            });
    });

    var request = new XMLHttpRequest();
    request.open('GET', '/wp-json/bp-cl-oauth/v1/has-access?uid='+uid+'&pid='+pid+'&data='+JSON.stringify(uniqueDownloadBtns), true);

    request.onload = function() {
        if (request.status >= 200 && request.status < 400) {
            var data = JSON.parse(request.responseText);
            if(data.hasOwnProperty('status') && data.status === 'OK') {
                setDownloadUrl(uniqueDownloadBtns, data.response);
            } else {
                setPaywall(downloadBtns);
            }
        }
    };
    request.send();
}

var loggedIn = getCookie('bp_cl_oauth_token');
var loginBtn = document.getElementById('user-navigation-btn');
var mobileLoginBtn = document.getElementById('user-mobile-navigation-btn');
var downloadBtns = document.getElementsByClassName('download-article-button');

if (loggedIn) {
    document.getElementById('user-navigation-btn-username').innerHTML = getCookie('bp_cl_oauth_username');
    loginBtn.setAttribute('href', loginBtn.getAttribute('data-profile'));
    mobileLoginBtn.setAttribute('href', getLogoutUrl());
    document.getElementById('user-mobile-navigation-label').innerHTML = 'Logout';

    if(downloadBtns.length) {
        checkAccess(downloadBtns);
    }
} else {
    loginBtn.setAttribute('href', getLoginUrl());
    mobileLoginBtn.setAttribute('href', getLoginUrl());
    setPaywall(downloadBtns);
}
