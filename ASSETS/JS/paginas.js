//recebendo array dos elementos com classe linkOpcoes
const linksOpcoes = document.querySelectorAll('.linkOpcoes');


//manipulacao do elemento clicado para troca de classe
linksOpcoes.forEach((link) =>{

    link.addEventListener('click', e => {
        // 1. Remove a classe de todos os links primeiro
        linksOpcoes.forEach(l => l.classList.remove('linkSelecionado'));

        // 2. Adiciona a classe apenas ao link clicado
        link.classList.add('linkSelecionado');
    });

})

