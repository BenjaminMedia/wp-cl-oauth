(function() {
    function getLoginUrl() {
        return routes['login'] + '?redirect_uri=' + encodeURIComponent(document.location.href);
    }

    function getLogoutUrl() {
        return routes['logout'] + '?redirect_uri=' + encodeURIComponent(document.location.href);
    }

    function getCookie(cname) {
        var name = cname + "=";
        var decodedCookie = decodeURIComponent(document.cookie);
        var ca = decodedCookie.split(';');
        for (var i = 0; i < ca.length; i++) {
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

    var loginBtn = document.getElementById('user-navigation-btn');
    var mobileLoginBtn = document.getElementById('user-mobile-navigation-btn');

    if (getCookie('bp_oauth_token')) {
        var loginBtnSpan = loginBtn.getElementsByTagName('span')[0];
        if(typeof loginBtnSpan !== 'undefined') {
            loginBtnSpan.innerHTML = getCookie('bp_oauth_username');
        }
        loginBtn.setAttribute('href', loginBtn.getAttribute('data-profile'));
        mobileLoginBtn.setAttribute('href', getLogoutUrl());
        var mobileLoginBtnSpan = mobileLoginBtn.getElementsByTagName('span')[0];
        if(typeof mobileLoginBtnSpan !== 'undefined') {
            mobileLoginBtnSpan.innerHTML = 'Logout';
        }
    } else {
        loginBtn.setAttribute('href', getLoginUrl());
        mobileLoginBtn.setAttribute('href', getLoginUrl());
    }
})();
