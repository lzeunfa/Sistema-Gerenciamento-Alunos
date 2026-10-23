// Teste de carga (escalabilidade) com k6: https://grafana.com/docs/k6/
// Rodado por docker/teste-carga.sh contra o container "app" (nunca contra o sistema real da academia).
//
// Cenarios:
//   recepcao  - administradores usando o sistema ao mesmo tempo: dashboard, filtros, paginacao, cadastro
//   logins    - pico de logins simultaneos (Argon2id usa ~64 MB e ~0,4 s de CPU por login, de proposito)
//
// Limites (thresholds): se algum falhar, o k6 termina com erro e o relatorio mostra qual.
import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Counter } from 'k6/metrics';

const BASE = __ENV.BASE_URL || 'http://app';
const USUARIO = __ENV.K6_USUARIO;
const SENHA = __ENV.K6_SENHA;
const cadastrosFeitos = new Counter('cadastros_feitos');

export const options = {
    // o k6 apaga os cookies a cada volta por padrao: cada "pessoa" seria deslogada depois da 1a volta
    noCookiesReset: true,
    scenarios: {
        recepcao: {
            executor: 'ramping-vus',
            exec: 'recepcao',
            startVUs: 1,
            stages: [
                { duration: '1m', target: 10 },  // academia movimentada
                { duration: '2m', target: 30 },  // muito acima do uso real (1 a 3 pessoas na recepcao)
                { duration: '1m', target: 30 },
                { duration: '30s', target: 0 },
            ],
        },
        logins: {
            executor: 'constant-arrival-rate',
            exec: 'loginSimultaneo',
            startTime: '5m',      // depois do cenario recepcao, para medir isolado
            rate: 5,              // 5 logins por segundo
            timeUnit: '1s',
            duration: '1m',
            preAllocatedVUs: 20,
            maxVUs: 60,
        },
    },
    thresholds: {
        'http_req_failed{scenario:recepcao}': ['rate<0.01'],         // menos de 1% de erro
        'http_req_duration{scenario:recepcao,tipo:pagina}': ['p(95)<800'], // 95% das paginas em menos de 0,8 s
        'http_req_duration{scenario:logins}': ['p(95)<3000'],         // login (Argon2id) em menos de 3 s
        'checks{scenario:recepcao}': ['rate>0.99'],
        'checks{scenario:logins}': ['rate>0.99'],
    },
};

function token(html) {
    const m = /name="csrf_token" value="([^"]+)"/.exec(html);
    return m ? m[1] : '';
}

function entrar() {
    const pagina = http.get(`${BASE}/index.php`, { tags: { tipo: 'pagina' } });
    const resposta = http.post(`${BASE}/BACKEND/CONTROLLER/actions.php`, {
        csrf_token: token(pagina.body),
        inputUser: USUARIO,
        inputUserPass: SENHA,
    }, { redirects: 0, tags: { tipo: 'login' } });
    return check(resposta, {
        'login redireciona para o sistema': (r) => r.status === 302 && String(r.headers.Location).includes('sistema.php'),
    });
}

// CPF valido e unico por iteracao (digitos verificadores calculados)
function cpfFicticio() {
    const d = [];
    for (let i = 0; i < 9; i++) d.push(Math.floor(Math.random() * 10));
    for (let t = 9; t < 11; t++) {
        let soma = 0;
        for (let i = 0; i < t; i++) soma += d[i] * (t + 1 - i);
        d.push(((10 * soma) % 11) % 10);
    }
    return d.join('');
}

export function recepcao() {
    if (__ITER === 0 && !entrar()) {
        return;
    }
    const pag = { tags: { tipo: 'pagina' } };

    group('dashboard', () => {
        const r = http.get(`${BASE}/sistema.php?pagina=DASHBOARD`, pag);
        check(r, { 'dashboard abre': (x) => x.status === 200 && x.body.includes('Total de alunos') });
    });
    sleep(1);

    group('alunos e filtros', () => {
        for (const filtro of ['', 'alunos_pendentes', 'alunos_pagos', 'plano_2', 'alunos_mulheres']) {
            const r = http.get(`${BASE}/sistema.php?pagina=ALUNOS&filtrando=${filtro}`, pag);
            check(r, { 'lista de alunos abre': (x) => x.status === 200 });
        }
        const r = http.get(`${BASE}/sistema.php?pagina=ALUNOS&p=2`, pag);
        check(r, { 'pagina 2 abre': (x) => x.status === 200 });
    });
    sleep(1);

    group('turmas', () => {
        const r = http.get(`${BASE}/sistema.php?pagina=TURMAS&filtrando=1`, pag);
        check(r, { 'turmas abre': (x) => x.status === 200 });
    });
    sleep(1);

    // 1 em cada 4 iteracoes cadastra um aluno ficticio, como a recepcao faria
    if (Math.random() < 0.25) {
        group('cadastro', () => {
            const form = http.get(`${BASE}/sistema.php?pagina=CADASTRAR`, pag);
            const r = http.post(`${BASE}/BACKEND/CONTROLLER/gravarAluno.php`, {
                csrf_token: token(form.body),
                nome: `CARGA ${__VU}-${__ITER}`,
                telefone: '61999990000',
                email: `carga.${__VU}.${__ITER}.${Date.now()}@exemplo.invalid`,
                cpf: cpfFicticio(),
                data_nascimento: '1995-03-20',
                genero: 'masculino',
                cep: '70000000',
                endereco: 'Rua Ficticia 1',
                'modalidades[]': '1',
                'plano-pagamento': '1',
                'frequencia-dias': '2',
                status: '1',
                ciencia_privacidade: '1',
            }, { redirects: 0, tags: { tipo: 'cadastro' } });
            const volta = http.get(`${BASE}/sistema.php?pagina=CADASTRAR`, pag);
            const ok = check(volta, {
                'cadastro gravado': (x) => r.status === 302 && x.body.includes('Aluno cadastrado com sucesso'),
            });
            if (ok) cadastrosFeitos.add(1);
        });
    }
    sleep(2);
}

export function loginSimultaneo() {
    http.cookieJar().clear(BASE); // cada login comeca sem sessao, como um navegador novo
    entrar();
}
