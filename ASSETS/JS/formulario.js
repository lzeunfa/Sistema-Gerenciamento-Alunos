// Ao entrar num campo de texto ja preenchido (tela Editar), o cursor vai para o final do texto,
// para continuar digitando sem apagar o comeco. So no primeiro clique: depois o cursor fica onde clicar.
document.addEventListener('focusin', function (evento) {
    const campo = evento.target;
    if (!campo.matches || !campo.matches('input.form-input')) {
        return;
    }
    // o navegador posiciona o cursor depois do evento de foco; espera ele terminar
    setTimeout(function () {
        const fim = campo.value.length;
        try {
            campo.setSelectionRange(fim, fim);
        } catch (erro) {
            // e-mail e data nao aceitam setSelectionRange; no e-mail, troca o tipo por um instante
            if (campo.type === 'email') {
                campo.type = 'text';
                campo.setSelectionRange(fim, fim);
                campo.type = 'email';
            }
        }
    }, 0);
});
