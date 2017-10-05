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

function setDownloadUrl(el, url)
{
    if(!el) { return; }
    el.setAttribute('href', url);
    el.setAttribute('target', '_blank');
    el.removeAttribute('data-toggle');
    el.removeAttribute('data-target');
    el.removeAttribute('disabled');
}

function setPaywall(el) {
    if(!el) { return; }
    el.setAttribute('data-target', '#modalPaywall');
    el.setAttribute('data-toggle', 'modal');
    el.removeAttribute('disabled');
}

function checkAccess(downloadTop, downloadBottom, downloadGallery)
{
    var id;
    var uid;
    if(downloadTop) {
        id = downloadTop.getAttribute('data-id');
        uid = downloadTop.getAttribute('data-uid');
    } else if(downloadBottom) {
        id = downloadBottom.getAttribute('data-id');
        uid = downloadBottom.getAttribute('data-uid');
    } else {
        id = downloadGallery.getAttribute('data-id');
        uid = downloadGallery.getAttribute('data-uid');
    }
    var request = new XMLHttpRequest();
    request.open('GET', '/wp-json/bp-cl-oauth/v1/has-access?id='+id+'&uid='+uid, true);

    request.onload = function() {
        if (request.status >= 200 && request.status < 400) {
            var data = JSON.parse(request.responseText);
            if(data.hasOwnProperty('status') && data.status === 'OK') {
                setDownloadUrl(downloadTop, data.url);
                setDownloadUrl(downloadBottom, data.url);
                setDownloadUrl(downloadGallery, data.url);
            } else {
                setPaywall(downloadTop);
                setPaywall(downloadBottom);
                setPaywall(downloadGallery);
            }
        }
    };
    request.send();
}

var loggedIn = getCookie('bp_cl_oauth_token');
var loginBtn = document.getElementById('user-navigation-btn');
var mobileLoginBtn = document.getElementById('user-mobile-navigation-btn');
var downloadTop = document.getElementById('download-article-btn-top');
var downloadBottom = document.getElementById('download-article-btn-bottom');
var downloadGallery = document.getElementById('download-article-btn-gallery');
if (loggedIn) {
    document.getElementById('user-navigation-btn-username').innerHTML = getCookie('bp_cl_oauth_username');
    loginBtn.setAttribute('href', loginBtn.getAttribute('data-profile'));
    mobileLoginBtn.setAttribute('href', getLogoutUrl());
    document.getElementById('user-mobile-navigation-label').innerHTML = 'Logout';
    if(downloadTop || downloadBottom || downloadGallery) {
        checkAccess(downloadTop, downloadBottom, downloadGallery);
    }
} else {
    loginBtn.setAttribute('href', getLoginUrl());
    mobileLoginBtn.setAttribute('href', getLoginUrl());
    setPaywall(downloadTop);
    setPaywall(downloadGallery);
    setPaywall(downloadBottom);
}
