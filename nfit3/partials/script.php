<?php require_once __DIR__ . '/../includes/bootstrap.php'; ?>
<?php $nfBaseUrl = function_exists('nf_base_url') ? nf_base_url() : ''; ?>
<?php $nfPricingUrl = function_exists('nf_url') ? nf_url('/pricing') : '/pricing'; ?>
<!-- Latest jQuery -->
<script src="assets/js/jquery-1.12.4.min.js"></script>
<!-- Latest compiled and minified Bootstrap -->
<script src="assets/bootstrap/js/bootstrap.min.js"></script>
<!-- modernizer JS -->
<script src="assets/js/modernizr-2.8.3.min.js"></script>
<!-- jquery-simple-mobilemenu.min -->
<script src="assets/js/jquery-simple-mobilemenu.js"></script>
<!-- owl-carousel min js  -->
<script src="assets/owlcarousel/js/owl.carousel.min.js"></script>
<!-- magnific-popup js -->
<script src="assets/js/jquery.magnific-popup.min.js"></script>
<!-- countTo js -->
<script src="assets/js/jquery.inview.min.js"></script>
<!-- GSAP AND LOCOMOTIV JS-->
<script src="assets/js/gsap.min.js"></script>
<script src="assets/js/ScrollTrigger.min.js"></script>
<!-- scrolltopcontrol js -->
<script src="assets/js/scrolltopcontrol.js"></script>
<!-- jquery.bubble js -->
<script src="assets/js/superMarquee.min.js"></script>
<!-- WOW - Reveal Animations When You Scroll -->
<script src="assets/js/wow.min.js"></script>
<!-- scripts js -->
<script src="assets/js/scripts.js"></script>
<?php echo (isset($script) ? $script : '') ?>
<script>
  (function () {
    var appBaseUrl = <?php echo json_encode(rtrim((string) $nfBaseUrl, '/'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?> || window.location.origin;
    var targetUrl = <?php echo json_encode((string) $nfPricingUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    var prodBase = 'https://nutremfit.com.br';
    var badPrefix = '/home2/edua0932/public_html/';
    var appBasePath = '';
    try {
      appBasePath = new URL(appBaseUrl, window.location.origin).pathname || '';
    } catch (e) {
      appBasePath = '';
    }
    if (appBasePath === '/') appBasePath = '';
    appBasePath = appBasePath.replace(/\/+$/, '');

    function compatPath(pathname) {
      if (!pathname || pathname === '/') return pathname || '/';
      if (/\.[a-z0-9]+$/i.test(pathname)) return pathname;
      if (!/^\/[a-z0-9-]+$/i.test(pathname)) return pathname;
      return pathname + '.php';
    }

    function splitUrlParts(url) {
      var m = String(url || '').match(/^([^?#]*)(\?[^#]*)?(#.*)?$/);
      return {
        path: (m && m[1]) || '',
        search: (m && m[2]) || '',
        hash: (m && m[3]) || ''
      };
    }

    function toAppScopedPath(pathname) {
      var path = pathname || '/';
      if (appBasePath && (path === appBasePath || path.indexOf(appBasePath + '/') === 0)) {
        path = path.slice(appBasePath.length) || '/';
      }
      return compatPath(path);
    }

    function rewriteUrlToApp(url) {
      if (!url) return null;

      if (url.indexOf(prodBase + '/') === 0) {
        try {
          var parsedProd = new URL(url);
          return appBaseUrl + toAppScopedPath(parsedProd.pathname) + (parsedProd.search || '') + (parsedProd.hash || '');
        } catch (e) {
          var tail = url.slice(prodBase.length);
          var prodParts = splitUrlParts(tail);
          return appBaseUrl + toAppScopedPath(prodParts.path || '/') + prodParts.search + prodParts.hash;
        }
      }

      if (url.charAt(0) === '/' && url.charAt(1) !== '/') {
        var localParts = splitUrlParts(url);
        return appBaseUrl + toAppScopedPath(localParts.path || '/') + localParts.search + localParts.hash;
      }

      return null;
    }

    (function fixBrokenPath() {
      var path = window.location.pathname || '';
      if (path.indexOf(badPrefix) === 0) {
        var cleanPath = '/' + path.slice(badPrefix.length);
        window.location.replace(appBaseUrl + compatPath(cleanPath));
      }
    })();

    function rewriteInternalProductionLinks() {
      var currentBase = appBaseUrl || window.location.origin;
      if (!currentBase) return;

      document.querySelectorAll('a[href], img[src], form[action]').forEach(function (el) {
        var attr = el.tagName === 'IMG' ? 'src' : (el.tagName === 'FORM' ? 'action' : 'href');
        var val = el.getAttribute(attr);
        if (!val || val === '#' || /^javascript:/i.test(val) || /^mailto:/i.test(val) || /^tel:/i.test(val)) return;

        var next = rewriteUrlToApp(val);
        if (!next) return;
        el.setAttribute(attr, next);
        if (attr === 'href' && 'href' in el) el.href = next;
        if (attr === 'src' && 'src' in el) el.src = next;
        if (attr === 'action' && 'action' in el) el.action = next;
      });
    }

    function patchNetworkApis() {
      if (window.fetch && !window.fetch.__nfWrapped) {
        var nativeFetch = window.fetch.bind(window);
        var wrappedFetch = function (input, init) {
          if (typeof input === 'string') {
            input = rewriteUrlToApp(input) || input;
          } else if (input && typeof input.url === 'string') {
            var rewritten = rewriteUrlToApp(input.url);
            if (rewritten) {
              try {
                input = new Request(rewritten, input);
              } catch (e) {
                // fallback: keep original Request if cloning is not supported
              }
            }
          }
          return nativeFetch(input, init);
        };
        wrappedFetch.__nfWrapped = true;
        window.fetch = wrappedFetch;
      }

      if (window.XMLHttpRequest && !window.XMLHttpRequest.prototype.__nfOpenWrapped) {
        var nativeOpen = window.XMLHttpRequest.prototype.open;
        window.XMLHttpRequest.prototype.open = function () {
          var args = Array.prototype.slice.call(arguments);
          if (typeof args[1] === 'string') {
            args[1] = rewriteUrlToApp(args[1]) || args[1];
          }
          return nativeOpen.apply(this, args);
        };
        window.XMLHttpRequest.prototype.__nfOpenWrapped = true;
      }
    }

    function fixHeroButton() {
      var buttons = document.querySelectorAll('a.btn_one, a');
      buttons.forEach(function (btn) {
        if (!btn || !btn.textContent) return;
        var text = btn.textContent.replace(/\s+/g, ' ').trim().toLowerCase();
        if (text === 'quero meu plano agora') {
          btn.setAttribute('href', targetUrl);
          btn.href = targetUrl;
          btn.addEventListener('click', function (e) {
            e.preventDefault();
            window.location.assign(targetUrl);
          }, { once: true });
        }
      });
    }

    patchNetworkApis();
    rewriteInternalProductionLinks();
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () {
        rewriteInternalProductionLinks();
        fixHeroButton();
      });
    } else {
      fixHeroButton();
    }
  })();
</script>
