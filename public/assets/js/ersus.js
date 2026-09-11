/* ERSUS360 — Frontend Application JS */
'use strict';

// ── Config ──────────────────────────────────────────────
const API = '';  // same origin
const TOKEN_KEY = 'ersus_token';
const USER_KEY  = 'ersus_user';

// ── Auth helpers ─────────────────────────────────────────
const Auth = {
  token: () => localStorage.getItem(TOKEN_KEY),
  user:  () => {
    try { return JSON.parse(localStorage.getItem(USER_KEY) || 'null'); }
    catch { localStorage.removeItem(USER_KEY); return null; }
  },
  save:  (token, user) => {
    localStorage.setItem(TOKEN_KEY, token);
    if (user != null) localStorage.setItem(USER_KEY, JSON.stringify(user));
  },
  clear: () => {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
  },
  isAuth: () => !!localStorage.getItem(TOKEN_KEY),
};

// ── API fetch wrapper ────────────────────────────────────
async function api(path, opts = {}) {
  const token = Auth.token();
  const res = await fetch(API + path, {
    ...opts,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: 'Bearer ' + token } : {}),
      ...opts.headers,
    },
    body: opts.body ? JSON.stringify(opts.body) : undefined,
  });

  if (res.status === 401) {
    Auth.clear();
    router.go('/login');
    return null;
  }

  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw { status: res.status, ...data };
  return data;
}

// ── Toast ────────────────────────────────────────────────
const Toast = {
  el: null,
  init() { this.el = document.getElementById('e-toast'); },
  show(msg, type = 'info', ms = 3500) {
    if (!this.el) return;
    this.el.className = 'show ' + type;
    this.el.querySelector('.e-toast-msg').textContent = msg;
    clearTimeout(this._t);
    this._t = setTimeout(() => { this.el.className = ''; }, ms);
  },
};

// ── Format helpers ───────────────────────────────────────
const Fmt = {
  brl: (v) => Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }),
  brlM: (v) => {
    const n = Number(v || 0);
    if (Math.abs(n) >= 1e6) return 'R$ ' + (n / 1e6).toFixed(2).replace('.', ',') + ' M';
    return Fmt.brl(n);
  },
  pct: (v) => Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%',
  date: (s) => s ? new Date(s + 'T00:00:00').toLocaleDateString('pt-BR') : '—',
  dateTime: (s) => s ? new Date(s).toLocaleString('pt-BR') : '—',
  mes: (s) => {
    if (!s) return '—';
    const [y, m] = s.split('-');
    const meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    return (meses[parseInt(m)-1] || m) + '/' + y;
  },
};

// ── Router ───────────────────────────────────────────────
const router = {
  routes: {},
  current: null,

  on(path, handler) { this.routes[path] = handler; },

  go(path, push = true) {
    if (push) history.pushState({}, '', path);
    this._dispatch(path);
  },

  _dispatch(path) {
    // Strip query string for route matching
    const base = path.split('?')[0];
    const handler = this.routes[base] || this.routes['/404'];
    if (!handler) return;

    // Auth guard
    if (base !== '/login' && !Auth.isAuth()) {
      this.go('/login');
      return;
    }
    if (base === '/login' && Auth.isAuth()) {
      this.go('/');
      return;
    }

    this.current = base;
    this._updateNav(base);
    handler(new URLSearchParams(path.split('?')[1] || ''));
  },

  _updateNav(path) {
    document.querySelectorAll('.e-nav-item').forEach(el => {
      el.classList.toggle('active', el.dataset.route === path);
    });
  },

  init() {
    window.addEventListener('popstate', () => this._dispatch(location.pathname));
    document.addEventListener('click', e => {
      const link = e.target.closest('[data-route]');
      if (!link) return;
      e.preventDefault();
      this.go(link.dataset.route);
    });
    this._dispatch(location.pathname);
  },
};

// ── Shell helpers ────────────────────────────────────────
function setMain(html) {
  document.getElementById('e-content').innerHTML = html;
}

function loading() {
  setMain('<div class="e-loading"><div class="e-spinner"></div> Carregando…</div>');
}

function error(msg) {
  setMain(`<div class="e-empty"><div class="e-empty-icon">⚠️</div><div class="e-empty-title">Erro ao carregar dados</div><p>${msg}</p></div>`);
}

// ── Diagnóstico padrão para módulos sem dados ─────────────
const DIAG = {
  fns: {
    titulo: '💰 Transferências FNS — sem dados',
    passos: [
      'Clique em <strong>Sincronizar FNS</strong> no topo da tela',
      'O sistema buscará os repasses do Fundo Nacional de Saúde para Apuí/AM',
      'Se o botão retornar erro, verifique as variáveis <code>MUNICIPIO_IBGE</code> e <code>MUNICIPIO_UF</code> no Railway',
    ],
    extra: 'Os repasses são públicos e não precisam de credenciais para serem consultados.',
  },
  aps: {
    titulo: '🏥 Cofinanciamento APS — sem dados',
    passos: [
      'Adicione <code>ESUS_USUARIO</code> e <code>ESUS_SENHA</code> nas variáveis do Railway (credenciais do e-Gestor APS)',
      'Clique em <strong>Sincronizar e-Gestor</strong> na tela de APS',
      'O sistema buscará os repasses dos Grupos C, B e M para a competência selecionada',
    ],
    extra: 'Credenciais são as mesmas usadas para acessar egestorab.saude.gov.br.',
  },
  emendas: {
    titulo: '🏛️ Emendas Parlamentares — sem dados',
    passos: [
      'Clique em <strong>+ Nova emenda</strong> para cadastrar manualmente, ou',
      'Configure a integração com o InvestSUS adicionando <code>INVESTSUS_TOKEN</code> no Railway',
      'Os dados do InvestSUS são atualizados mensalmente pelo MS',
    ],
    extra: 'Emendas parlamentares do município podem ser consultadas em investsus.saude.gov.br.',
  },
  portarias: {
    titulo: '📄 Portarias DOU — sem dados',
    passos: [
      'Clique em <strong>Sincronizar DOU</strong> na tela de Portarias',
      'O sistema busca portarias do Diário Oficial relacionadas a Apuí/AM e à APS',
      'A busca é automática e não precisa de credenciais',
    ],
    extra: 'Portarias são buscadas na API pública do Diário Oficial da União (in.gov.br).',
  },
  folha: {
    titulo: '👥 Folha de Presença — sem dados',
    passos: [
      'Acesse o sistema Python antigo e exporte o arquivo <code>folha_referencia.json</code>',
      'Use a opção de importação legacy em <strong>Administração → Importar Folha</strong>',
      'Após a importação, os funcionários e histórico de presença estarão disponíveis',
    ],
    extra: 'Dados históricos do sistema anterior podem ser migrados sem perda de informações.',
  },
  cnes: {
    titulo: '🏥 CNES — sem dados',
    passos: [
      'Adicione <code>CNES_TOKEN</code> nas variáveis do Railway (token de acesso à API do CNES)',
      'Clique em <strong>Sincronizar CNES</strong> na tela de estabelecimentos',
      'O sistema buscará estabelecimentos, equipes e profissionais de Apuí/AM',
    ],
    extra: 'O token CNES é obtido no portal datasus.gov.br — seção desenvolvedores.',
  },
  indicadores: {
    titulo: '📊 Mapa de Desempenho — sem dados suficientes',
    passos: [
      'Sincronize os módulos APS, FNS e CNES para alimentar os indicadores',
      'O score ERSUS 360 é calculado automaticamente com base nos dados disponíveis',
      'Módulos com mais dados geram indicadores mais precisos',
    ],
    extra: 'O score é um indicador gerencial interno — não equivale ao ranking oficial DAB/MS.',
  },
};

function diagCard(modulo) {
  const d = DIAG[modulo];
  if (!d) return '';
  return `
    <div style="border:1.5px dashed #94a3b8;border-radius:10px;padding:20px 22px;margin-top:16px;background:color-mix(in srgb,currentColor 3%,transparent)">
      <div style="font-weight:700;font-size:14px;margin-bottom:10px;color:var(--text)">${d.titulo}</div>
      <p style="font-size:13px;margin:0 0 10px;color:var(--muted)">Nenhum dado encontrado. Para ativar este módulo:</p>
      <ol style="font-size:13px;margin:0 0 12px;padding-left:20px;line-height:2;color:var(--text)">
        ${d.passos.map(p => `<li>${p}</li>`).join('')}
      </ol>
      <p style="font-size:12px;color:var(--muted);margin:0">💡 ${d.extra}</p>
    </div>`;
}

// ── Dashboard page ───────────────────────────────────────
async function pageDashboard() {
  loading();
  try {
    const user = Auth.user();
    const mid  = user?.municipio_id || 1;

    const [dash, alertas] = await Promise.all([
      api(`/api/municipios/${mid}/dashboard`),
      api('/api/alertas?por_pagina=5'),
    ]);

    const d = dash || {};
    const resumo = d.resumo || {};

    setMain(`
      <div class="e-page-header">
        <h1 class="e-page-title">🏠 Início</h1>
        <p class="e-page-sub">Visão geral da Secretaria Municipal de Saúde — ${d.nome || 'Apuí'}/AM</p>
      </div>

      <div class="e-hero">
        <div>
          <h1>ERSUS<span>360</span></h1>
          <p>${d.nome || 'Secretaria Municipal de Saúde'} · IBGE ${d.ibge || '1300144'}</p>
        </div>
        <div class="e-hero-stats">
          <div class="e-hero-stat">
            <div class="e-hero-stat-n" id="dash-repasses">${Fmt.brlM(resumo.total_transferencias)}</div>
            <div class="e-hero-stat-l">Repasses FNS</div>
          </div>
          <div class="e-hero-stat">
            <div class="e-hero-stat-n">${resumo.total_portarias || 0}</div>
            <div class="e-hero-stat-l">Portarias</div>
          </div>
          <div class="e-hero-stat">
            <div class="e-hero-stat-n">${resumo.alertas_ativos || 0}</div>
            <div class="e-hero-stat-l">Alertas ativos</div>
          </div>
        </div>
      </div>

      <h2 class="e-card-title mb-3" style="font-family:Syne,sans-serif;font-size:15px;font-weight:700;color:var(--text)">Módulos disponíveis</h2>

      <div class="e-module-grid mb-4">
        <a class="e-module-card" data-route="/indicadores">
          <div class="e-module-icon">📊</div>
          <div class="e-module-name">Mapa de Desempenho</div>
          <div class="e-module-desc">Score ERSUS 360 e ranking regional</div>
        </a>
        <a class="e-module-card" data-route="/aps">
          <div class="e-module-icon">🏥</div>
          <div class="e-module-name">Cofinanciamento APS</div>
          <div class="e-module-desc">Grupos eSF, eSB, eMulti, Ribeirinha</div>
        </a>
        <a class="e-module-card" data-route="/fns">
          <div class="e-module-icon">💰</div>
          <div class="e-module-name">Transferências FNS</div>
          <div class="e-module-desc">Repasses e execução por bloco</div>
        </a>
        <a class="e-module-card" data-route="/fns/emendas">
          <div class="e-module-icon">🏛️</div>
          <div class="e-module-name">Emendas Parlamentares</div>
          <div class="e-module-desc">Propostas, empenho e pagamento</div>
        </a>
        <a class="e-module-card" data-route="/fns/portarias">
          <div class="e-module-icon">📋</div>
          <div class="e-module-name">Portarias DOU</div>
          <div class="e-module-desc">Diário Oficial — alertas automáticos</div>
        </a>
        <a class="e-module-card" data-route="/folha">
          <div class="e-module-icon">👥</div>
          <div class="e-module-name">Folha de Presença</div>
          <div class="e-module-desc">ACS e equipes — registro mensal</div>
        </a>
        <a class="e-module-card" data-route="/cnes">
          <div class="e-module-icon">🏢</div>
          <div class="e-module-name">CNES</div>
          <div class="e-module-desc">Estabelecimentos e equipes de saúde</div>
        </a>
        <a class="e-module-card soon" title="Em breve">
          <div class="e-module-icon">💊</div>
          <div class="e-module-name">Assist. Farmacêutica</div>
          <div class="e-module-desc">Em breve — estoque e dispensação</div>
        </a>
        <a class="e-module-card soon" title="Em breve">
          <div class="e-module-icon">📅</div>
          <div class="e-module-name">Planejamento</div>
          <div class="e-module-desc">Em breve — PMS, RAG, Contratos</div>
        </a>
      </div>

      ${alertas?.dados?.length ? `
      <div class="e-card">
        <div class="e-card-header"><h3 class="e-card-title">🔔 Alertas recentes</h3></div>
        <div class="e-card-body p-0">
          <table class="e-table">
            <tbody>
              ${alertas.dados.map(a => `
                <tr>
                  <td>${a.titulo || a.descricao}</td>
                  <td>${a.municipio_nome || ''}</td>
                  <td><span class="e-badge e-badge-${a.nivel === 'critico' ? 'red' : a.nivel === 'atencao' ? 'amber' : 'blue'}">${a.nivel || 'info'}</span></td>
                  <td class="mono" style="color:var(--muted)">${Fmt.dateTime(a.criado_em)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>` : ''}
    `);
  } catch(e) {
    error(e.message || 'Não foi possível carregar o dashboard.');
  }
}

// ── FNS — Transferências ─────────────────────────────────
async function pageFns(params) {
  loading();
  try {
    const user = Auth.user();
    const mid  = user?.municipio_id || 1;
    const ano  = params.get('ano') || new Date().getFullYear();

    const [lista, resumo] = await Promise.all([
      api(`/api/fns?municipio_id=${mid}&ano=${ano}&por_pagina=50`),
      api(`/api/fns/resumo?municipio_id=${mid}&ano=${ano}`),
    ]);

    const r = resumo || {};
    const dados = lista?.dados || [];

    setMain(`
      <div class="e-page-header">
        <h1 class="e-page-title">💰 Transferências FNS</h1>
        <p class="e-page-sub">Fundo Nacional de Saúde — repasses recebidos por bloco de financiamento</p>
      </div>

      <div class="e-stats">
        <div class="e-stat">
          <div class="e-stat-label">Total recebido ${ano}</div>
          <div class="e-stat-val accent">${Fmt.brl(r.total_ano)}</div>
        </div>
        <div class="e-stat">
          <div class="e-stat-label">Transferências</div>
          <div class="e-stat-val">${r.qtd_transferencias || dados.length}</div>
        </div>
        <div class="e-stat">
          <div class="e-stat-label">Último repasse</div>
          <div class="e-stat-val" style="font-size:16px">${r.ultimo_repasse ? Fmt.mes(r.ultimo_repasse) : '—'}</div>
        </div>
      </div>

      <div class="e-filters">
        <select class="e-select" onchange="router.go('/fns?ano='+this.value)">
          ${[2024,2025,2026].map(y => `<option value="${y}" ${y==ano?'selected':''}>${y}</option>`).join('')}
        </select>
        <a class="e-btn e-btn-outline e-btn-sm" href="/api/fns/exportar?municipio_id=${mid}&ano=${ano}" target="_blank">⬇ Exportar CSV</a>
        <button class="e-btn e-btn-primary e-btn-sm" onclick="sincFns()">↻ Sincronizar</button>
      </div>

      <div class="e-card">
        <div class="e-card-header"><h3 class="e-card-title">Repasses recebidos</h3></div>
        <div class="e-table-wrap">
          <table class="e-table">
            <thead>
              <tr>
                <th>Competência</th>
                <th>Bloco</th>
                <th>Componente / Programa</th>
                <th style="text-align:right">Valor Líquido</th>
                <th>Data Crédito</th>
                <th>Situação</th>
              </tr>
            </thead>
            <tbody>
              ${dados.length ? dados.map(f => {
                const comp = (f.competencia || '').substring(0, 7);
                const sit  = f.situacao || 'Pago';
                const sitBadge = /cancelad|estorno/i.test(sit)
                  ? 'e-badge-red' : /pendente|agendad/i.test(sit)
                  ? 'e-badge-amber' : 'e-badge-green';
                return `
                <tr>
                  <td class="mono">${comp || '—'}</td>
                  <td style="max-width:220px;white-space:normal;font-size:12px">${f.bloco || '—'}</td>
                  <td style="font-size:12px">${f.componente || f.programa || '—'}</td>
                  <td style="text-align:right;font-weight:700;color:var(--accent)">${Fmt.brl(f.valor_liquido ?? f.valor_bruto ?? 0)}</td>
                  <td style="font-size:12px;color:var(--muted)">${f.data_credito ? new Date(f.data_credito).toLocaleDateString('pt-BR') : '—'}</td>
                  <td><span class="e-badge ${sitBadge}">${sit}</span></td>
                </tr>`;
              }).join('') : `<tr><td colspan="6" style="padding:0"><div class="e-empty"><div class="e-empty-icon">📭</div><div class="e-empty-title">Nenhum repasse encontrado</div><p>Clique em Sincronizar para buscar os dados do FNS.</p></div></td></tr>`}
            </tbody>
          </table>
        </div>
      </div>
      ${!dados.length ? diagCard('fns') : ''}
    `);

    window.sincFns = async () => {
      Toast.show('Sincronizando FNS…', 'info');
      try {
        const r = await api('/api/fns/sincronizar', { method: 'POST', body: { municipio_id: mid } });
        Toast.show(`✔ ${r?.novos || 0} novos registros`, 'success');
        pageFns(params);
      } catch(e) { Toast.show('Erro: ' + e.message, 'error'); }
    };
  } catch(e) { error(e.message); }
}

// ── APS — Cofinanciamento ────────────────────────────────
async function pageAps(params) {
  loading();
  try {
    const user = Auth.user();
    const mid  = user?.municipio_id || 1;
    const ano  = params.get('ano') || new Date().getFullYear();
    const compAtual = params.get('competencia') || (() => {
      const d = new Date(); d.setMonth(d.getMonth() - 1);
      return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
    })();

    const [lista, resumo, diagResp] = await Promise.all([
      api(`/api/aps?municipio_id=${mid}&ano=${ano}`),
      api(`/api/aps/resumo?municipio_id=${mid}&ano=${ano}`),
      api(`/api/aps/diagnostico?municipio_id=${mid}&competencia=${compAtual}`).catch(e => ({ _erro: e.message })),
    ]);

    const r = resumo || {};
    const dados = lista?.dados || lista || [];
    const diag  = diagResp || {};
    const diagErro = diag._erro || diag.erro;

    // ── Detecção local de AR ausente (funciona sem API do e-Gestor) ──
    // A tabela repasses_aps já tem os dados sincronizados: eMulti com C e Q mas sem AR
    const todosComponentes = dados.map(d =>
      (d.componente || d.subcomponente || d.bloco || d.grupo || '').toLowerCase()
    );
    const temEmultiLocal = todosComponentes.some(c =>
      c.includes('emulti') || c.includes('multiprofissional') || c.includes('grupo m')
    );
    const temARLocal = todosComponentes.some(c =>
      c.includes('remoto') || c.includes('ar') || c.includes('telessaude') || c.includes('teleassist')
    );
    // Também checa via valor: eMulti em dados com C=12000 e Q=2250 mas sem AR
    const emultiRows = dados.filter(d => /emulti|multiprofissional/i.test(
      d.bloco || d.componente || d.grupo || ''
    ));
    const emultiTotal = emultiRows.reduce((s, d) => s + (parseFloat(d.valor_total || d.valor || 0)), 0);
    // R$ 14.250 = C(12000) + Q(2250) sem AR nem V → diagnóstico local confiável
    const arAusenteLocal = emultiRows.length > 0 && (temEmultiLocal && !temARLocal);

    // Issues construídas localmente (sempre disponíveis)
    const issuesLocais = [];
    if (arAusenteLocal) {
      issuesLocais.push({
        severidade: 'atencao',
        titulo: 'eMulti: Atendimento Remoto (AR) não recebido',
        descricao: 'A equipe eMulti recebe Custeio (C) e Qualidade (Q), mas o componente AR — Atendimento Remoto / Telessaúde — não está sendo pago pelo e-Gestor. Isso indica ausência de produção de teleconsultas registrada na RNDS ou modalidade não habilitada.',
        impacto: 'Perda potencial de R$ 5.000,00/mês por modalidade habilitada.',
        requisitos: [
          'A equipe eMulti deve estar cadastrada com modalidade habilitada para teleassistência no e-Gestor',
          'Registrar atividades de teleconsulta ou telediagnóstico no e-SUS PEC (ficha de atendimento com tipo "Telessaúde")',
          'Produção mínima de 20 teleconsultas/mês deve constar na RNDS',
          'Verificar habilitação da modalidade junto ao DAB/MS — pode ser necessário enviar ofício ao COSEMS',
        ],
        acao_url: 'https://egestorab.saude.gov.br',
        acao_label: 'Verificar no e-Gestor APS',
      });
    }

    // Merge: usa inconsistências da API quando disponíveis, senão usa locais
    const issues = (diag.inconsistencias && diag.inconsistencias.length > 0)
      ? diag.inconsistencias
      : issuesLocais;

    // Monta painel de inconsistências do e-Gestor
    function renderDiagEgestor() {
      if (diagErro && issues.length === 0) {
        // API falhou E sem dados locais para diagnóstico
        return '';
      }

      // issues já calculado acima (merge API + local)
      const painel  = diag.painel || {};
      const compsEM = diag.componentes || {};
      const inds    = painel.indicadores || {};

      const sevBg     = s => s === 'critico' ? '#fee2e2' : '#fef3c7';
      const sevBorder = s => s === 'critico' ? '#ef4444' : '#f59e0b';

      const issuesHtml = issues.map(issue => `
        <div style="border-left:4px solid ${sevBorder(issue.severidade)};background:${sevBg(issue.severidade)};border-radius:0 8px 8px 0;padding:14px 16px;margin-bottom:12px">
          <div style="font-weight:700;font-size:14px;margin-bottom:6px;color:#1e293b">${issue.severidade === 'critico' ? '🚨' : '⚠️'} ${issue.titulo}</div>
          <p style="font-size:13px;margin:0 0 8px;color:#374151">${issue.descricao}</p>
          <p style="font-size:12px;color:#6b7280;margin:0 0 8px"><strong>Impacto financeiro:</strong> ${issue.impacto}</p>
          <ol style="font-size:13px;margin:0 0 10px;padding-left:20px;line-height:1.8;color:#374151">
            ${(issue.requisitos || []).map(r=>`<li>${r}</li>`).join('')}
          </ol>
          ${issue.acao_url ? `<a href="${issue.acao_url}" target="_blank" class="e-btn e-btn-sm e-btn-outline" style="font-size:12px">${issue.acao_label || 'Verificar'}</a>` : ''}
        </div>`).join('');

      // ── Tabela completa de equipes (igual ao sistema antigo) ──
      const linhas = painel.linhas || [];
      const indsHtml = [
        ['Equidade ESF',    inds.equidade_esf],
        ['Vínculo ESF/eAP', inds.vinculo_esf_eap],
        ['Qualidade ESF',   inds.qualidade_esf],
        ['Qualidade eMulti',inds.qualidade_emulti],
      ].filter(([,v]) => v).map(([l,v]) => `
        <div style="border:1px solid var(--border);border-radius:8px;padding:10px 14px;min-width:140px">
          <div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em">${l}</div>
          <div style="margin-top:6px"><span class="e-badge e-badge-green">${v}</span></div>
        </div>`).join('');

      const linhasHtml = linhas.length ? linhas.map(l => {
        const semEquipes = l.qtdPagas === 0 || l.qtdPagas === '0';
        return `
        <tr style="${semEquipes ? 'opacity:.55' : ''}">
          <td style="font-weight:500">${l.label}</td>
          <td style="text-align:center">${l.qtdPagas ?? '—'}</td>
          <td style="text-align:center">${l.teto ?? '—'}</td>
          <td style="text-align:right;font-weight:700;color:${l.valor > 0 ? 'var(--accent)' : 'var(--muted)'}">${l.valor > 0 ? Fmt.brl(l.valor) : '—'}</td>
          <td style="font-size:12px;color:var(--muted)">${l.detalhes || (semEquipes ? 'Sem equipes pagas' : '')}</td>
        </tr>`;
      }).join('') : `
        <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:16px">
          Dados de equipes não disponíveis neste formato de resposta do e-Gestor.
        </td></tr>`;

      // ── Componentes eMulti ──
      const emHtml = Object.values(compsEM).map(c => {
        const badge = c.status === 'recebendo'
          ? `<span class="e-badge e-badge-green">✔ Recebendo</span>`
          : `<span class="e-badge e-badge-gray">✖ Não recebendo</span>`;
        return `<tr>
          <td>${c.label}</td>
          <td><span class="e-badge e-badge-blue">${c.sigla}</span></td>
          <td style="text-align:right">${c.valor_ref ? Fmt.brl(c.valor_ref) + '/mês' : 'Variável'}</td>
          <td style="text-align:right;font-weight:600">${c.valor > 0 ? Fmt.brl(c.valor) : '—'}</td>
          <td>${badge}</td>
        </tr>`;
      }).join('');

      const coletadoEm = painel.coletado_em || diag.coletado_em;

      return `
        ${issues.length ? `
        <div class="e-card mt-3" style="border-left:4px solid #f59e0b">
          <div class="e-card-header"><h3 class="e-card-title">🔍 Inconsistências — e-Gestor ${compAtual}</h3></div>
          <div style="padding:12px 16px">${issuesHtml}</div>
        </div>` : `
        <div class="e-card mt-3" style="border-left:4px solid #22c55e">
          <div class="e-card-header"><h3 class="e-card-title">✅ Sem inconsistências em ${compAtual}</h3></div>
          <div style="padding:12px 16px;font-size:13px;color:var(--muted)">Todos os componentes estão recebendo normalmente.</div>
        </div>`}

        <div class="e-card mt-3">
          <div class="e-card-header">
            <h3 class="e-card-title">📊 Equipes e Indicadores — ${compAtual}</h3>
          </div>
          ${indsHtml ? `<div style="display:flex;gap:12px;flex-wrap:wrap;padding:12px 16px 0">${indsHtml}</div>` : ''}
          <div class="e-table-wrap" style="padding:8px 16px 16px">
            <table class="e-table" style="font-size:13px">
              <thead><tr><th>Componente</th><th style="text-align:center">Qtd Pagas</th><th style="text-align:center">Teto</th><th style="text-align:right">Valor Total</th><th>Detalhes</th></tr></thead>
              <tbody>${linhasHtml}</tbody>
            </table>
            ${coletadoEm ? `
            <p style="font-size:11px;color:var(--muted);margin-top:10px">
              Fonte: e-Gestor APS (tipoRelatorio=COMPLETO) · Coletado em: ${new Date(coletadoEm).toLocaleString('pt-BR')}
              &nbsp;·&nbsp;<a href="https://egestorab.saude.gov.br" target="_blank" style="color:var(--accent)">Ver no e-Gestor APS ↗</a>
            </p>` : ''}
          </div>
        </div>

        <div class="e-card mt-3" style="border-left:4px solid #6366f1">
          <div class="e-card-header"><h3 class="e-card-title">📋 Componentes eMulti — Portaria 3.493/2024</h3></div>
          <div class="e-table-wrap" style="padding:0 16px 16px">
            <table class="e-table" style="font-size:13px">
              <thead><tr><th>Componente</th><th>Sigla</th><th style="text-align:right">Valor ref.</th><th style="text-align:right">Recebido</th><th>Status</th></tr></thead>
              <tbody>${emHtml || `
                <tr><td>Custeio / Implantação</td><td><span class="e-badge e-badge-blue">C</span></td><td style="text-align:right">R$ 12.000/mês</td><td style="text-align:right">—</td><td><span class="e-badge e-badge-gray">Aguardando</span></td></tr>
                <tr><td>Qualidade</td><td><span class="e-badge e-badge-blue">Q</span></td><td style="text-align:right">Variável</td><td style="text-align:right">—</td><td><span class="e-badge e-badge-gray">Aguardando</span></td></tr>
                <tr><td>Atendimento Remoto</td><td><span class="e-badge e-badge-amber">AR</span></td><td style="text-align:right">R$ 5.000/mês</td><td style="text-align:right">—</td><td><span class="e-badge e-badge-gray">Aguardando</span></td></tr>
                <tr><td>Vínculo</td><td><span class="e-badge e-badge-gray">V</span></td><td style="text-align:right">Variável</td><td style="text-align:right">—</td><td><span class="e-badge e-badge-gray">Aguardando</span></td></tr>`}
              </tbody>
            </table>
          </div>
        </div>`;
    }

    setMain(`
      <div class="e-page-header">
        <h1 class="e-page-title">🏥 Cofinanciamento APS</h1>
        <p class="e-page-sub">Portaria 3.493/2024 — Grupos eSF, eSB, eMulti, Ribeirinha</p>
      </div>

      <div class="e-stats">
        <div class="e-stat">
          <div class="e-stat-label">Total APS ${ano}</div>
          <div class="e-stat-val accent">${Fmt.brl(r.total)}</div>
        </div>
        <div class="e-stat">
          <div class="e-stat-label">Grupo C (eSF/eAP)</div>
          <div class="e-stat-val">${Fmt.brl(r.grupo_c)}</div>
        </div>
        <div class="e-stat">
          <div class="e-stat-label">Grupo B (eSB)</div>
          <div class="e-stat-val">${Fmt.brl(r.grupo_b)}</div>
        </div>
        <div class="e-stat">
          <div class="e-stat-label">Grupo M (eMulti)</div>
          <div class="e-stat-val">${Fmt.brl(r.grupo_m)}</div>
        </div>
      </div>

      <div class="e-filters">
        <select class="e-select" onchange="router.go('/aps?ano='+this.value)">
          ${[2024,2025,2026].map(y => `<option value="${y}" ${y==ano?'selected':''}>${y}</option>`).join('')}
        </select>
        <button class="e-btn e-btn-primary e-btn-sm" onclick="sincAps()">↻ Sincronizar e-Gestor</button>
        <a class="e-btn e-btn-outline e-btn-sm" href="/api/aps/exportar?municipio_id=${mid}&ano=${ano}" target="_blank">⬇ Exportar</a>
      </div>

      <div class="e-card">
        <div class="e-card-header"><h3 class="e-card-title">Repasses por competência</h3></div>
        <div class="e-table-wrap">
          <table class="e-table">
            <thead>
              <tr>
                <th>Competência</th>
                <th>Bloco</th>
                <th>Grupo</th>
                <th style="text-align:right">Valor</th>
                <th>Situação</th>
              </tr>
            </thead>
            <tbody>
              ${dados.length ? dados.map(d => `
                <tr>
                  <td class="mono">${d.competencia || Fmt.mes(d.mes_referencia)}</td>
                  <td>${d.bloco || 'APS'}</td>
                  <td><span class="e-badge e-badge-blue">${d.grupo || '—'}</span></td>
                  <td style="text-align:right;font-weight:600">${Fmt.brl(d.valor)}</td>
                  <td><span class="e-badge e-badge-green">OK</span></td>
                </tr>
              `).join('') : `<tr><td colspan="5" class="text-center py-4" style="color:var(--muted)">Nenhum dado encontrado — sincronize com o e-Gestor.</td></tr>`}
            </tbody>
          </table>
        </div>
      </div>

      ${renderDiagEgestor()}
    `);

    window.sincAps = async () => {
      Toast.show('Sincronizando e-Gestor…', 'info');
      try {
        await api('/api/aps/sincronizar', { method: 'POST', body: { municipio_id: mid } });
        Toast.show('Dados atualizados', 'success');
        pageAps(params);
      } catch(e) { Toast.show('Erro: ' + e.message, 'error'); }
    };
  } catch(e) { error(e.message); }
}

// ── Emendas Parlamentares ────────────────────────────────
async function pageEmendas(params) {
  loading();
  try {
    const user = Auth.user();
    const mid  = user?.municipio_id || 1;
    const ano  = params.get('ano') || new Date().getFullYear();

    const [lista, resumo] = await Promise.all([
      api(`/api/emendas?municipio_id=${mid}&ano_orcamentario=${ano}&por_pagina=50`),
      api(`/api/emendas/resumo?municipio_id=${mid}&ano=${ano}`),
    ]);

    const r = resumo?.totais || {};
    const porFase = resumo?.por_fase || [];
    const dados = lista?.dados || [];

    const faseColor = { proposta:'gray', aprovada:'blue', empenhada:'amber', liquidada:'amber', paga:'green', cancelada:'red' };

    setMain(`
      <div class="e-page-header">
        <h1 class="e-page-title">🏛️ Emendas Parlamentares</h1>
        <p class="e-page-sub">InvestSUS — acompanhamento de propostas e execução orçamentária</p>
      </div>

      <div class="e-stats">
        <div class="e-stat">
          <div class="e-stat-label">Total emendas</div>
          <div class="e-stat-val">${r.total_emendas || dados.length}</div>
        </div>
        <div class="e-stat">
          <div class="e-stat-label">Valor autorizado</div>
          <div class="e-stat-val accent">${Fmt.brl(r.autorizado)}</div>
        </div>
        <div class="e-stat">
          <div class="e-stat-label">Valor empenhado</div>
          <div class="e-stat-val amber">${Fmt.brl(r.empenhado)}</div>
        </div>
        <div class="e-stat">
          <div class="e-stat-label">Valor pago</div>
          <div class="e-stat-val green">${Fmt.brl(r.pago)}</div>
        </div>
      </div>

      <div class="e-filters">
        <select class="e-select" onchange="router.go('/fns/emendas?ano='+this.value)">
          ${[2023,2024,2025,2026].map(y => `<option value="${y}" ${y==ano?'selected':''}>${y}</option>`).join('')}
        </select>
        <button class="e-btn e-btn-primary e-btn-sm" onclick="novaEmenda()">+ Nova emenda</button>
      </div>

      <div class="e-card">
        <div class="e-card-header">
          <h3 class="e-card-title">Emendas ${ano}</h3>
          ${porFase.map(f => `<span class="e-badge e-badge-${faseColor[f.fase]||'gray'} ms-2">${f.fase} (${f.qtd})</span>`).join('')}
        </div>
        <div class="e-table-wrap">
          <table class="e-table">
            <thead>
              <tr>
                <th>Nº Emenda</th>
                <th>Parlamentar</th>
                <th>Objeto</th>
                <th>Tipo</th>
                <th>Fase</th>
                <th style="text-align:right">Empenhado</th>
                <th style="text-align:right">Pago</th>
              </tr>
            </thead>
            <tbody>
              ${dados.length ? dados.map(e => `
                <tr>
                  <td class="mono">${e.numero_emenda || '—'}</td>
                  <td>${e.parlamentar || e.autor || '—'}</td>
                  <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${e.objeto || '—'}</td>
                  <td><span class="e-badge e-badge-gray">${e.tipo}</span></td>
                  <td><span class="e-badge e-badge-${faseColor[e.fase]||'gray'}">${e.fase}</span></td>
                  <td style="text-align:right">${Fmt.brl(e.valor_empenhado)}</td>
                  <td style="text-align:right;font-weight:600;color:var(--green)">${Fmt.brl(e.valor_pago)}</td>
                </tr>
              `).join('') : `<tr><td colspan="7"><div class="e-empty"><div class="e-empty-icon">🏛️</div><div class="e-empty-title">Nenhuma emenda cadastrada</div><p>Clique em "+ Nova emenda" para adicionar.</p></div></td></tr>`}
            </tbody>
          </table>
        </div>
      </div>
      ${!dados.length ? diagCard('emendas') : ''}
    `);

    window.novaEmenda = () => Toast.show('Formulário de nova emenda — em breve', 'info');
  } catch(e) { error(e.message); }
}

// ── Portarias DOU ────────────────────────────────────────
async function pagePortarias(params) {
  loading();
  try {
    const user = Auth.user();
    const mid  = user?.municipio_id || 1;

    const lista = await api(`/api/portarias?municipio_id=${mid}&por_pagina=30`);
    const dados = lista?.dados || [];

    setMain(`
      <div class="e-page-header">
        <h1 class="e-page-title">📋 Portarias DOU</h1>
        <p class="e-page-sub">Diário Oficial da União — monitoramento automático de publicações</p>
      </div>

      <div class="e-filters">
        <button class="e-btn e-btn-primary e-btn-sm" onclick="sincPortarias()">↻ Verificar DOU hoje</button>
      </div>

      <div class="e-card">
        <div class="e-card-header">
          <h3 class="e-card-title">Publicações encontradas</h3>
          <span class="e-badge e-badge-blue ms-2">${dados.length} portarias</span>
        </div>
        <div class="e-table-wrap">
          <table class="e-table">
            <thead>
              <tr>
                <th>Título</th>
                <th>Seção</th>
                <th>Data DOU</th>
                <th>Lida</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              ${dados.length ? dados.map(p => `
                <tr>
                  <td style="max-width:300px">
                    <div style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${p.titulo || '—'}</div>
                    <div style="font-size:11px;color:var(--muted);margin-top:2px">${(p.resumo||'').substring(0,100)}…</div>
                  </td>
                  <td><span class="e-badge e-badge-gray">${p.secao_dou || '—'}</span></td>
                  <td class="mono">${Fmt.date(p.data_publicacao)}</td>
                  <td>${p.lida ? '<span class="e-badge e-badge-green">✔ Lida</span>' : '<span class="e-badge e-badge-amber">Pendente</span>'}</td>
                  <td>
                    ${p.url_dou ? `<a href="${p.url_dou}" target="_blank" class="e-btn e-btn-outline e-btn-sm">Abrir DOU</a>` : ''}
                    ${!p.lida ? `<button class="e-btn e-btn-outline e-btn-sm ms-1" onclick="marcarLida(${p.id})">Marcar lida</button>` : ''}
                  </td>
                </tr>
              `).join('') : `<tr><td colspan="5"><div class="e-empty"><div class="e-empty-icon">📰</div><div class="e-empty-title">Nenhuma portaria encontrada</div><p>Clique em "Verificar DOU hoje" para buscar publicações.</p></div></td></tr>`}
            </tbody>
          </table>
        </div>
      </div>
      ${!dados.length ? diagCard('portarias') : ''}
    `);

    window.sincPortarias = async () => {
      Toast.show('Consultando Diário Oficial…', 'info');
      try {
        const r = await api('/api/portarias/sincronizar', { method: 'POST', body: { municipio_id: mid } });
        Toast.show(`✔ ${r?.novas || 0} novas portarias`, 'success');
        pagePortarias(params);
      } catch(e) { Toast.show('Erro: ' + e.message, 'error'); }
    };

    window.marcarLida = async (id) => {
      try {
        await api(`/api/portarias/${id}/lida`, { method: 'PUT' });
        Toast.show('Marcada como lida', 'success');
        pagePortarias(params);
      } catch(e) { Toast.show('Erro', 'error'); }
    };
  } catch(e) { error(e.message); }
}

// ── Folha de Presença ────────────────────────────────────
async function pageFolha(params) {
  loading();
  try {
    const user = Auth.user();
    const mid  = user?.municipio_id || 1;
    const hoje = new Date();
    const mes  = params.get('mes') || `${hoje.getFullYear()}-${String(hoje.getMonth()+1).padStart(2,'0')}`;

    const [funcs, resumo] = await Promise.all([
      api(`/api/folha/funcionarios?municipio_id=${mid}&mes=${mes}`),
      api(`/api/folha/presenca?municipio_id=${mid}&mes=${mes}`),
    ]);

    const lista = funcs?.dados || funcs || [];
    const statusColor = { ativo:'green', afastado:'amber', ferias:'blue', inativo:'gray' };

    setMain(`
      <div class="e-page-header">
        <h1 class="e-page-title">👥 Folha de Presença</h1>
        <p class="e-page-sub">Registro mensal de presença — ACS e equipes de saúde</p>
      </div>

      <div class="e-filters">
        <input type="month" class="e-input" value="${mes}" onchange="router.go('/folha?mes='+this.value)">
        <button class="e-btn e-btn-primary e-btn-sm" onclick="exportarFolha()">⬇ Exportar CSV</button>
        <button class="e-btn e-btn-outline e-btn-sm" onclick="adicionarFunc()">+ Funcionário</button>
      </div>

      <div class="e-stats">
        <div class="e-stat">
          <div class="e-stat-label">Total funcionários</div>
          <div class="e-stat-val">${lista.length}</div>
        </div>
        <div class="e-stat">
          <div class="e-stat-label">Presentes</div>
          <div class="e-stat-val green">${lista.filter(f => f.presente).length}</div>
        </div>
        <div class="e-stat">
          <div class="e-stat-label">Ausentes/Afastados</div>
          <div class="e-stat-val amber">${lista.filter(f => !f.presente && f.status !== 'inativo').length}</div>
        </div>
      </div>

      <div class="e-card">
        <div class="e-card-header">
          <h3 class="e-card-title">Presenças — ${Fmt.mes(mes)}</h3>
        </div>
        <div class="e-table-wrap">
          <table class="e-table">
            <thead>
              <tr>
                <th>Matrícula</th>
                <th>Nome</th>
                <th>Cargo</th>
                <th>Status</th>
                <th>Presença</th>
                <th>Observação</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              ${lista.length ? lista.map(f => `
                <tr>
                  <td class="mono">${f.matricula}</td>
                  <td style="font-weight:500">${f.nome}</td>
                  <td style="color:var(--muted)">${f.cargo || '—'}</td>
                  <td><span class="e-badge e-badge-${statusColor[f.status]||'gray'}">${f.status}</span></td>
                  <td>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                      <input type="checkbox" ${f.presente ? 'checked' : ''} onchange="togglePresenca(${f.id||"'"+f.matricula+"'"}, this.checked, '${mes}')">
                      <span>${f.presente ? 'Presente' : 'Ausente'}</span>
                    </label>
                  </td>
                  <td><input class="e-input" style="width:160px;padding:4px 8px;font-size:12px" value="${f.observacao||''}" placeholder="observação…" onblur="salvarObs(${f.id||"'"+f.matricula+"'"}, this.value, '${mes}')"></td>
                  <td></td>
                </tr>
              `).join('') : `<tr><td colspan="7"><div class="e-empty"><div class="e-empty-icon">👤</div><div class="e-empty-title">Nenhum funcionário cadastrado</div></div></td></tr>`}
            </tbody>
          </table>
        </div>
      </div>
      ${!lista.length ? diagCard('folha') : ''}
    `);

    window.togglePresenca = async (id, presente, mes) => {
      try {
        await api('/api/folha/presenca', { method: 'POST', body: { municipio_id: mid, matricula: id, mes_referencia: mes, presente } });
        Toast.show('Presença salva', 'success');
      } catch(e) { Toast.show('Erro ao salvar', 'error'); }
    };

    window.salvarObs = async (id, obs, mes) => {
      try {
        await api('/api/folha/presenca', { method: 'POST', body: { municipio_id: mid, matricula: id, mes_referencia: mes, observacao: obs } });
      } catch(_) {}
    };

    window.exportarFolha = () => {
      window.open(`/api/folha/exportar?municipio_id=${mid}&mes=${mes}`, '_blank');
    };

    window.adicionarFunc = () => Toast.show('Formulário de funcionário — em breve', 'info');
  } catch(e) { error(e.message); }
}

// ── CNES ─────────────────────────────────────────────────
async function pageCnes(params) {
  loading();
  try {
    const user = Auth.user();
    const mid  = user?.municipio_id || 1;

    const dados = await api(`/api/cnes/estabelecimentos?municipio_id=${mid}`);
    const lista = dados?.dados || dados || [];

    setMain(`
      <div class="e-page-header">
        <h1 class="e-page-title">🏢 CNES</h1>
        <p class="e-page-sub">Cadastro Nacional de Estabelecimentos de Saúde — Apuí/AM</p>
      </div>

      <div class="e-filters">
        <button class="e-btn e-btn-primary e-btn-sm" onclick="sincCnes()">↻ Sincronizar CNES</button>
      </div>

      <div class="e-card">
        <div class="e-card-header">
          <h3 class="e-card-title">Estabelecimentos</h3>
          <span class="e-badge e-badge-blue ms-2">${lista.length} unidades</span>
        </div>
        <div class="e-table-wrap">
          <table class="e-table">
            <thead>
              <tr>
                <th>CNES</th>
                <th>Nome</th>
                <th>Tipo</th>
                <th>Esfera</th>
                <th>Equipes</th>
              </tr>
            </thead>
            <tbody>
              ${lista.length ? lista.map(e => `
                <tr>
                  <td class="mono">${e.cnes || e.codigo_cnes || '—'}</td>
                  <td style="font-weight:500">${e.nome || e.nome_fantasia || '—'}</td>
                  <td><span class="e-badge e-badge-gray">${e.tipo || '—'}</span></td>
                  <td>${e.esfera || '—'}</td>
                  <td>${e.qtd_equipes || 0}</td>
                </tr>
              `).join('') : `<tr><td colspan="5"><div class="e-empty"><div class="e-empty-icon">🏥</div><div class="e-empty-title">Dados não carregados</div><p>Clique em "Sincronizar CNES" para buscar os estabelecimentos.</p></div></td></tr>`}
            </tbody>
          </table>
        </div>
      </div>
      ${!lista.length ? diagCard('cnes') : ''}
    `);

    window.sincCnes = async () => {
      Toast.show('Sincronizando CNES…', 'info');
      try {
        await api('/api/cnes/sincronizar', { method: 'POST', body: { municipio_id: mid } });
        Toast.show('CNES atualizado', 'success');
        pageCnes(params);
      } catch(e) { Toast.show('Erro: ' + e.message, 'error'); }
    };
  } catch(e) { error(e.message); }
}

// ── Indicadores / Mapa de Desempenho ─────────────────────
async function pageIndicadores() {
  loading();
  try {
    const user = Auth.user();
    const mid  = user?.municipio_id || 1;

    const dados = await api(`/api/indicadores/dashboard?municipio_id=${mid}`);
    const d = dados || {};

    const score = d.score || 76;
    const circumf = 2 * Math.PI * 42;
    const pct = (score / 100) * circumf;

    setMain(`
      <div class="e-page-header">
        <h1 class="e-page-title">📊 Mapa de Desempenho</h1>
        <p class="e-page-sub">Score ERSUS 360 — indicador gerencial interno · não equivale ao ranking oficial MS/DAB</p>
      </div>

      <div style="display:grid;grid-template-columns:auto 1fr;gap:24px;margin-bottom:28px">
        <div class="e-card" style="text-align:center;padding:28px 36px">
          <div style="font-size:12px;color:var(--muted);margin-bottom:12px;font-weight:600;letter-spacing:.05em;text-transform:uppercase">Score ERSUS 360</div>
          <div style="position:relative;width:120px;height:120px;margin:0 auto 12px">
            <svg width="120" height="120" viewBox="0 0 100 100" style="transform:rotate(-90deg)">
              <circle cx="50" cy="50" r="42" fill="none" stroke="var(--border)" stroke-width="8"/>
              <circle cx="50" cy="50" r="42" fill="none" stroke="var(--accent)" stroke-width="8"
                stroke-dasharray="${pct.toFixed(1)} ${circumf.toFixed(1)}"
                stroke-linecap="round"/>
            </svg>
            <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center">
              <span style="font-family:Syne,sans-serif;font-size:30px;font-weight:800;color:var(--accent);line-height:1">${score}</span>
              <span style="font-size:11px;color:var(--muted)">pts</span>
            </div>
          </div>
          <div style="font-family:Syne,sans-serif;font-size:16px;font-weight:700;color:var(--text)">${d.classificacao || 'Bom'}</div>
          <div style="font-size:12px;color:var(--muted);margin-top:4px">Meta 2026: 80 pts</div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;align-content:start">
          ${[
            { label: 'Ranking Sul/AM', val: d.ranking ? '#'+d.ranking : '#1', sub: 'de 7 municípios', color: 'accent' },
            { label: 'Evolução 2026', val: (d.evolucao > 0 ? '+' : '') + (d.evolucao || '+14.4'), sub: 'vs Jan/2026', color: 'green' },
            { label: 'Dimensões OK', val: d.dimensoes_ok || 3, sub: 'APS · Financeiro · Gestão', color: '' },
            { label: 'Alertas ativos', val: d.alertas || 0, sub: 'sem pendências críticas', color: '' },
          ].map(s => `
            <div class="e-stat">
              <div class="e-stat-label">${s.label}</div>
              <div class="e-stat-val ${s.color}">${s.val}</div>
              <div class="e-stat-sub">${s.sub}</div>
            </div>
          `).join('')}
        </div>
      </div>

      <div class="e-card">
        <div class="e-card-header"><h3 class="e-card-title">Composição por dimensão</h3></div>
        <div class="e-card-body">
          ${[
            { nome:'APS / SIAPS Sprint ÓTIMO', peso:35, score: d.dim_aps || 83, cor:'accent' },
            { nome:'Financeiro / FNS',          peso:25, score: d.dim_fin || 81, cor:'green'  },
            { nome:'Epidemiologia / Vigilância', peso:20, score: d.dim_epi || 65, cor:'amber'  },
            { nome:'Gestão / RH / Obras',       peso:10, score: d.dim_gest|| 70, cor:'accent' },
            { nome:'Infraestrutura / Patrimônio',peso:10, score: d.dim_infra||62, cor:'red'   },
          ].map(dim => `
            <div style="margin-bottom:16px">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                <span style="font-size:13px;font-weight:500">${dim.nome} <span style="color:var(--muted);font-size:11px">peso ${dim.peso}%</span></span>
                <span style="font-family:Syne,sans-serif;font-size:16px;font-weight:700;color:var(--${dim.cor})">${dim.score}</span>
              </div>
              <div class="e-prog">
                <div class="e-prog-bar ${dim.cor}" style="width:${dim.score}%"></div>
              </div>
            </div>
          `).join('')}
        </div>
      </div>
      ${!dados ? diagCard('indicadores') : ''}
    `);
  } catch(e) { error(e.message); }
}

// ── Alertas ───────────────────────────────────────────────
async function pageAlertas() {
  loading();
  try {
    const dados = await api('/api/alertas?por_pagina=50');
    const lista = dados?.dados || [];

    const nivelColor = { critico:'red', atencao:'amber', info:'blue', ok:'green' };

    setMain(`
      <div class="e-page-header">
        <h1 class="e-page-title">🔔 Alertas</h1>
        <p class="e-page-sub">Notificações automáticas do sistema</p>
      </div>

      <div class="e-filters">
        ${lista.filter(a => !a.lido).length > 0 ? `
          <button class="e-btn e-btn-outline e-btn-sm" onclick="marcarTodos()">✔ Marcar todos como lidos</button>
        ` : ''}
      </div>

      <div style="display:flex;flex-direction:column;gap:10px">
        ${lista.length ? lista.map(a => `
          <div class="e-card" style="${a.lido ? 'opacity:.6' : ''}">
            <div style="padding:16px 20px;display:flex;align-items:center;gap:14px">
              <span style="font-size:22px">${a.nivel==='critico'?'🚨':a.nivel==='atencao'?'⚠️':'ℹ️'}</span>
              <div style="flex:1;min-width:0">
                <div style="font-weight:600;margin-bottom:2px">${a.titulo || a.descricao}</div>
                <div style="font-size:12px;color:var(--muted)">${a.descricao !== a.titulo ? a.descricao || '' : ''}</div>
              </div>
              <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                <span class="e-badge e-badge-${nivelColor[a.nivel]||'gray'}">${a.nivel}</span>
                <span style="font-size:11px;color:var(--muted)">${Fmt.dateTime(a.criado_em)}</span>
                ${!a.lido ? `<button class="e-btn e-btn-outline e-btn-sm" onclick="lerAlerta(${a.id})">Marcar lido</button>` : ''}
              </div>
            </div>
          </div>
        `).join('') : `<div class="e-empty"><div class="e-empty-icon">✅</div><div class="e-empty-title">Sem alertas ativos</div><p>Tudo em ordem.</p></div>`}
      </div>
    `);

    window.lerAlerta = async (id) => {
      try {
        await api(`/api/alertas/${id}/lido`, { method: 'PUT' });
        pageAlertas();
      } catch(_) {}
    };

    window.marcarTodos = async () => {
      try {
        await api('/api/alertas/marcar-todos', { method: 'POST' });
        Toast.show('Todos os alertas marcados como lidos', 'success');
        pageAlertas();
      } catch(e) { Toast.show('Erro', 'error'); }
    };
  } catch(e) { error(e.message); }
}

// ── Usuários (admin) ─────────────────────────────────────
async function pageUsuarios() {
  loading();
  try {
    const dados = await api('/api/usuarios?por_pagina=50');
    const lista = dados?.dados || [];

    const perfilColor = { superadmin:'red', admin:'amber', gestor:'blue', coordenador:'blue', consulta:'gray' };

    setMain(`
      <div class="e-page-header">
        <h1 class="e-page-title">👤 Usuários</h1>
        <p class="e-page-sub">Gerenciamento de acesso ao sistema</p>
      </div>

      <div class="e-filters">
        <button class="e-btn e-btn-primary e-btn-sm" onclick="novoUsuario()">+ Novo usuário</button>
      </div>

      <div class="e-card">
        <div class="e-table-wrap">
          <table class="e-table">
            <thead>
              <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Perfil</th>
                <th>Município</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              ${lista.length ? lista.map(u => `
                <tr>
                  <td style="font-weight:500">${u.nome}</td>
                  <td style="color:var(--muted)">${u.email}</td>
                  <td><span class="e-badge e-badge-${perfilColor[u.perfil]||'gray'}">${u.perfil}</span></td>
                  <td>${u.municipio_nome || '—'}</td>
                  <td>${u.ativo ? '<span class="e-badge e-badge-green">Ativo</span>' : '<span class="e-badge e-badge-gray">Inativo</span>'}</td>
                  <td>
                    <button class="e-btn e-btn-outline e-btn-sm" onclick="editarUsuario(${u.id})">Editar</button>
                  </td>
                </tr>
              `).join('') : `<tr><td colspan="6"><div class="e-empty"><div class="e-empty-icon">👤</div><div class="e-empty-title">Nenhum usuário cadastrado</div></div></td></tr>`}
            </tbody>
          </table>
        </div>
      </div>
    `);

    window.novoUsuario  = () => Toast.show('Formulário de usuário — em breve', 'info');
    window.editarUsuario = () => Toast.show('Edição de usuário — em breve', 'info');
  } catch(e) { error(e.message); }
}

// ── Login page ───────────────────────────────────────────
function pageLogin() {
  document.getElementById('e-shell').style.display = 'none';
  document.getElementById('e-login').style.display  = 'flex';
}

async function doLogin(e) {
  e.preventDefault();
  const form  = e.target;
  const email = form.email.value;
  const senha = form.senha.value;
  const btn   = form.querySelector('button[type=submit]');

  btn.disabled = true;
  btn.textContent = 'Entrando…';
  document.getElementById('login-err').textContent = '';

  try {
    const data = await fetch('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, senha }),
    });

    const json = await data.json();
    if (!data.ok) throw new Error(json.erro || json.message || 'Credenciais inválidas');

    Auth.save(json.token, json.usuario);
    document.getElementById('e-shell').style.display = '';
    document.getElementById('e-login').style.display  = 'none';

    // Update topbar user info
    const u = json.usuario;
    document.getElementById('tb-user-name').textContent = u.nome?.split(' ')[0] || 'Usuário';
    document.getElementById('tb-user-avatar').textContent = (u.nome||'U')[0].toUpperCase();

    router.go('/');
  } catch(err) {
    document.getElementById('login-err').textContent = err.message;
  } finally {
    btn.disabled = false;
    btn.textContent = 'Entrar';
  }
}

// ── Init ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  Toast.init();

  // Restore user info in topbar if logged in
  const u = Auth.user();
  if (u) {
    document.getElementById('tb-user-name').textContent = u.nome?.split(' ')[0] || 'Usuário';
    document.getElementById('tb-user-avatar').textContent = (u.nome||'U')[0].toUpperCase();
    if (u.municipio_nome) {
      document.getElementById('tb-muni').textContent = u.municipio_nome + '/AM';
      document.getElementById('sb-muni-name').textContent = u.municipio_nome;
    }
  }

  // Logout
  document.getElementById('btn-logout').addEventListener('click', () => {
    Auth.clear();
    router.go('/login');
  });

  // Register routes
  router.on('/',                pageDashboard);
  router.on('/aps',             (p) => pageAps(p));
  router.on('/fns',             (p) => pageFns(p));
  router.on('/fns/emendas',     (p) => pageEmendas(p));
  router.on('/fns/portarias',   (p) => pagePortarias(p));
  router.on('/folha',           (p) => pageFolha(p));
  router.on('/cnes',            pageCnes);
  router.on('/indicadores',     pageIndicadores);
  router.on('/alertas',         pageAlertas);
  router.on('/admin/usuarios',  pageUsuarios);
  router.on('/login',           pageLogin);

  router.init();
});
