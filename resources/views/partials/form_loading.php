<script>
(function () {
  // Estado de "enviando" automático em qualquer <form> com submit nativo:
  // bloqueia duplo envio e mostra spinner no botão. Cobre todos os forms
  // (e os futuros) sem precisar editar cada view.
  //
  // Opt-out: adicione o atributo data-no-loading ao <form>.
  // Não usamos o atributo `disabled` de propósito — isso removeria o
  // name/value do botão do POST (quebraria forms com múltiplos submits).
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.hasAttribute('data-no-loading')) return;

    // Já está enviando → bloqueia segundo envio (Enter ou clique repetido).
    if (form.dataset.submitting === '1') { e.preventDefault(); return; }

    // confirm() cancelado ou Alpine @submit.prevent → não marca como enviando.
    if (e.defaultPrevented) return;

    form.dataset.submitting = '1';

    var btn = form.querySelector('button[type="submit"], button:not([type]), input[type="submit"]');
    if (!btn) return;

    btn.classList.add('opacity-60', 'cursor-wait', 'pointer-events-none');

    if (btn.tagName === 'BUTTON') {
      var spinner = document.createElement('span');
      spinner.className = 'inline-block w-3.5 h-3.5 mr-2 align-middle border-2 border-current border-r-transparent rounded-full animate-spin';
      btn.prepend(spinner);
    }
  });
})();
</script>
