		<!-- START PRELOADER -->
		<div class="preloaders" aria-label="Carregando">
			<div class="loader">
        <svg viewBox="0 0 120 120" role="presentation" aria-hidden="true">
          <path class="kb-path" d="m79.35 48.69 1.97-3.75c4.62-8.79 4.45-19.21-.45-27.87C76.04 8.53 67.33 3.09 57.59 2.54c-.47-.03-.93-.04-1.35-.04h-20.4c-.42 0-.87.01-1.35.04-9.74.56-18.45 5.99-23.28 14.53-4.9 8.66-5.07 19.08-.45 27.87l1.93 3.68a43.344 43.344 0 0 0-10.2 28.01c0 .44 0 .87.02 1.3.53 18.22 15.78 32.57 34.01 32.57h19.04c18.2 0 33.44-14.31 34-32.5.01-.43.02-.87.02-1.3.02-10.61-3.85-20.4-10.24-28.01Zm-32.27-15.6a43.373 43.373 0 0 0-25.67 7.63l-.57-1.08a17.622 17.622 0 0 1 .28-16.96c2.96-5.23 8.07-8.43 14.02-8.77.25-.01.49-.02.7-.02h20.4c.22 0 .45 0 .7.02 5.95.34 11.06 3.54 14.02 8.77 2.98 5.27 3.09 11.62.28 16.96l-.59 1.12c-6.74-4.66-14.84-7.47-23.57-7.67Z"></path>
        </svg>
        <span class="sr-only">Carregando...</span>
      </div>
		</div>
		<script>
		  (function() {
            const startedAt = Date.now();
            const minDisplayMs = 900;   // tempo mínimo visível para o usuário perceber a animação
            const maxDisplayMs = 2600;  // garante que não atrapalha a interação
            let hidden = false;

		    const hide = () => {
              if (hidden) return;
		      const el = document.querySelector('.preloaders');
		      if (el) {
		        el.classList.add('is-hidden');
		        hidden = true;
		        setTimeout(() => { if (el.parentNode) el.parentNode.removeChild(el); }, 600);
		      }
		    };

            window.addEventListener('load', () => {
              const elapsed = Date.now() - startedAt;
              const waitMore = Math.max(minDisplayMs - elapsed, 0);
              setTimeout(hide, waitMore);
            });

		    // fallback: mesmo sem evento de load, some rápido
            setTimeout(hide, maxDisplayMs);
		  })();
		</script>
		<!-- END PRELOADER -->		
