//recebendo a tag main que recebe a troca de conteudos html
const main = document.querySelector('main');

//recebendo os links do menu
const linksMenu = document.querySelectorAll('.linkOpcoes');

//variaveis que recebem alteracao de texto
//1- nome topo
//2- caminho
const paginaAtual = document.querySelector('#nomePA');
const caminhoPaginaAtual = document.querySelector('#caminhoPA');
/*para cada link é criado um evento de click
onde é recebido seu atributo href destinado a pagina com conteudo html
chamando a funcao converterHTML para poder usar o html
e substituindo no html da tag main*/
linksMenu.forEach(link => {
    
    link.addEventListener('click', async(e)=>{
        e.preventDefault();

        // 1. Remove a classe de todos os links primeiro
        linksMenu.forEach(l => l.classList.remove('linkSelecionado'));

        // 2. Adiciona a classe apenas ao link clicado
        link.classList.add('linkSelecionado');

        //dividi o caminho para reaproveitar nas trocas de texto no cabecalho da pagina
        const url = 'ASSETS/PAGINAS/';
        const pagina = link.getAttribute('href');
        const tipoArquivo = '.html';

        //trocas de textos no cabecalho da pagina
        paginaAtual.textContent = pagina.toLocaleLowerCase();
        caminhoPaginaAtual.textContent = pagina.toLowerCase();

        main.innerHTML = await converterHTML(url+pagina+tipoArquivo);

    })

})

//funcao parar carregar o arquivo do dashboard ao carregar a pagina do sistema.html
async function aoCarregarPagina(){
    
    //Adiciona a classe apenas ao link dashboard
    linksMenu[0].classList.add('linkSelecionado');
}

//funcao que recuperar o arquivo destinado com html
//e que torna possivel o uso do html dentro do mesmo
async function converterHTML(arquivo){
    try {

        const response = await fetch(arquivo);
        const htmlBruto = await response.text();

        return htmlBruto;

    } catch (error) {
        
        console.error('Erro ao carregar a página', error);
        const htmlError = "<h2>Erro ao carregar conteúdo</h2>";

        return htmlError;

    }
}