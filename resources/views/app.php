<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ERSUS 360 · Sistema de Gestão em Saúde</title>
  <meta name="description" content="Sistema de gestão em saúde municipal — Apuí/AM">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Inter:ital,wght@0,400;0,500;0,600;1,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="/assets/css/app.css">

  <style>
    /* Collapsible nav groups */
    .e-nav-sub { overflow:hidden; max-height:0; transition:max-height .25s ease; }
    .e-nav-sub.open { max-height:1200px; }
    .e-nav-group-btn {
      display:flex; align-items:center; gap:9px; padding:8px 12px;
      margin:1px 8px; border-radius:7px; color:#6888a8; font-size:13px;
      font-weight:600; cursor:pointer; border:none; background:none;
      width:calc(100% - 16px); text-align:left; transition:all .15s;
      font-family:inherit;
    }
    .e-nav-group-btn:hover { background:rgba(37,99,235,.1); color:#bdd4ff; }
    .e-nav-group-btn .e-nav-arrow { margin-left:auto; font-size:10px; transition:transform .2s; color:#2a3f5f; }
    .e-nav-group-btn.open .e-nav-arrow { transform:rotate(90deg); }
    .e-nav-group-btn .e-nav-badge { margin-left:auto; }
  </style>
</head>
<body>

<!-- ── Login overlay ─────────────────────────────────── -->
<div id="e-login" class="e-login-wrap" style="display:none">
  <div class="e-login-card">
    <div class="e-login-logo">ERSUS<span>360</span></div>
    <div class="e-login-sub">Sistema de Gestão em Saúde · Apuí/AM</div>

    <form id="login-form" onsubmit="doLogin(event)">
      <div class="e-form-group">
        <label class="e-form-label" for="login-email">E-mail</label>
        <input class="e-input w-100" id="login-email" name="email" type="email"
               placeholder="seu@email.gov.br" autocomplete="username" required>
      </div>
      <div class="e-form-group">
        <label class="e-form-label" for="login-senha">Senha</label>
        <input class="e-input w-100" id="login-senha" name="senha" type="password"
               placeholder="••••••••" autocomplete="current-password" required>
      </div>
      <div id="login-err" style="color:var(--red);font-size:12px;min-height:18px;margin-bottom:8px"></div>
      <button type="submit" class="e-btn e-btn-primary w-100" style="justify-content:center">
        Entrar no Sistema
      </button>
    </form>

    <p style="text-align:center;font-size:11px;color:var(--muted2);margin-top:20px">
      ERSUS360 v2.1 · PHP 8.4 · Uso restrito à equipe autorizada
    </p>
  </div>
</div>

<!-- ── App shell ──────────────────────────────────────── -->
<div id="e-shell">

  <!-- Topbar -->
  <header class="e-topbar">
    <button id="btn-sidebar-toggle" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:18px;padding:4px 6px;display:none" onclick="document.getElementById('e-sidebar').classList.toggle('open')">☰</button>
    <a href="/" data-route="/" class="e-logo">ERSUS<span>360</span></a>
    <div class="e-topbar-sep"></div>
    <span class="e-muni" id="tb-muni">Apuí · AM</span>

    <div class="e-topbar-right">
      <button class="e-alert-btn" data-route="/alertas" title="Alertas" onclick="router.go('/alertas')">
        🔔
        <span class="e-alert-badge" id="tb-alert-dot" style="display:none"></span>
      </button>

      <button class="e-user-btn" id="btn-user-menu">
        <div class="e-user-avatar" id="tb-user-avatar">U</div>
        <span id="tb-user-name">Usuário</span>
        <span style="color:#64748b;font-size:10px">▾</span>
      </button>

      <button id="btn-logout" class="e-btn e-btn-outline e-btn-sm" style="color:#94a3b8;border-color:rgba(255,255,255,.15)">
        Sair
      </button>
    </div>
  </header>

  <!-- Sidebar + Main -->
  <div class="e-shell" style="display:flex">

    <!-- Sidebar -->
    <nav class="e-sidebar" id="e-sidebar">

      <!-- Municipality card -->
      <div class="e-sidebar-muni">
        <div class="e-sidebar-muni-name" id="sb-muni-name">Apuí</div>
        <div class="e-sidebar-muni-sub">AM · IBGE 1300144</div>
        <div class="e-sidebar-score">
          <div class="e-sidebar-score-n" id="sb-score">—</div>
          <div class="e-sidebar-score-label">Score<br>ERSUS 360°</div>
        </div>
      </div>

      <!-- ── Início ── -->
      <div class="e-nav-group">
        <button class="e-nav-item" data-route="/">
          <span class="e-nav-icon">⚕️</span> Início
        </button>
        <button class="e-nav-item" data-route="/dashboard">
          <span class="e-nav-icon">📊</span> Visão Executiva
          <span class="e-nav-badge" id="sb-alert-count" style="display:none">0</span>
        </button>
      </div>

      <!-- ── Atenção Primária ── -->
      <div class="e-nav-group">
        <span class="e-nav-label">Atenção Primária</span>
        <button class="e-nav-item" data-route="/aps">
          <span class="e-nav-icon">🏥</span> Painel APS
        </button>
        <button class="e-nav-item" data-route="/aps/cofinanciamento">
          <span class="e-nav-icon">💊</span> Cofinanciamento APS
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">🎯</span> Sprint ÓTIMO
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">🌿</span> Saúde Brasil 360
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">📋</span> Matriz Normativa
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">🤝</span> Acolhimento
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">👩‍⚕️</span> ACS
          <span class="e-nav-soon">em breve</span>
        </button>
      </div>

      <!-- ── Financeiro ── -->
      <div class="e-nav-group">
        <span class="e-nav-label">Financeiro e Gestão Fiscal</span>
        <button class="e-nav-item" data-route="/financeiro">
          <span class="e-nav-icon">💼</span> Painel Financeiro
        </button>
        <button class="e-nav-item" data-route="/fns">
          <span class="e-nav-icon">💰</span> Controle FNS
        </button>
        <button class="e-nav-item" data-route="/fns/emendas">
          <span class="e-nav-icon">🏛️</span> Emendas Parlamentares
        </button>
        <button class="e-nav-item" data-route="/fns/portarias">
          <span class="e-nav-icon">📋</span> Portarias FNS
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">📑</span> SIOPS / SICONFI
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">📊</span> RREO Anexo 12
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">🏦</span> Fundo Municipal
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">🔍</span> Transparência LAI
          <span class="e-nav-soon">em breve</span>
        </button>
      </div>

      <!-- ── FNS / Convênios ── -->
      <div class="e-nav-group">
        <span class="e-nav-label">FNS / Convênios</span>
        <button class="e-nav-item" data-route="/fns/execucao">
          <span class="e-nav-icon">📈</span> Execução por Bloco
        </button>
        <button class="e-nav-item" data-route="/fns/convenios">
          <span class="e-nav-icon">🤝</span> Convênios FNS
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">💹</span> InvestSUS
          <span class="e-nav-soon">em breve</span>
        </button>
      </div>

      <!-- ── Planejamento ── -->
      <div class="e-nav-group">
        <span class="e-nav-label">Planejamento e Prestação de Contas</span>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">📅</span> Plano Municipal
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">📝</span> RAG / Prestação
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">📋</span> RDQA
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">⚖️</span> Conselho de Saúde
          <span class="e-nav-soon">em breve</span>
        </button>
      </div>

      <!-- ── Vigilância ── -->
      <div class="e-nav-group">
        <span class="e-nav-label">Vigilância em Saúde</span>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">💉</span> Vacinas / SIPNI
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">🦟</span> Arboviroses / Dengue
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">🦠</span> Epidemiologia / SINAN
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">🧬</span> TB / Hanseníase
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">🦟</span> Malária / Endemias
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">🐕</span> Zoonoses / Vetores
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">🔬</span> Vigilância Sanitária
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">💊</span> SISVAN
          <span class="e-nav-soon">em breve</span>
        </button>
      </div>

      <!-- ── Saúde do Cidadão ── -->
      <div class="e-nav-group">
        <span class="e-nav-label">Saúde do Cidadão</span>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">👴</span> Saúde do Idoso
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">👶</span> Saúde da Criança
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">👩</span> Saúde da Mulher
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">🧠</span> Saúde Mental / RAPS
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">🦷</span> Saúde Bucal / CEO
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">🏥</span> Atenção Especializada
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">🚑</span> Urgência / SAMU 192
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">💊</span> Farmácia Básica
          <span class="e-nav-soon">em breve</span>
        </button>
      </div>

      <!-- ── Gestão de Pessoas ── -->
      <div class="e-nav-group">
        <span class="e-nav-label">Administração do Sistema</span>
        <button class="e-nav-item" data-route="/rh">
          <span class="e-nav-icon">👥</span> RH
        </button>
        <button class="e-nav-item" data-route="/folha">
          <span class="e-nav-icon">📅</span> Folha de Pagamento
        </button>
        <button class="e-nav-item" data-route="/cnes">
          <span class="e-nav-icon">🏢</span> CNES
        </button>
        <button class="e-nav-item" data-route="/admin/usuarios">
          <span class="e-nav-icon">👤</span> Usuários & Perfis
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">⚙️</span> Integrações
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">🔗</span> Gateway RNDS · FHIR R4
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.4;cursor:default">
          <span class="e-nav-icon">📲</span> Integração PEC e-SUS
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" data-route="/alertas">
          <span class="e-nav-icon">🔔</span> Central de Alertas
          <span class="e-nav-badge" id="sb-alert-count-2" style="display:none">0</span>
        </button>
        <button class="e-nav-item" data-route="/admin/auditoria">
          <span class="e-nav-icon">📋</span> Auditoria
        </button>
      </div>

    </nav>

    <!-- Main content -->
    <main class="e-main" id="e-content">
      <div class="e-loading"><div class="e-spinner"></div> Carregando…</div>
    </main>

  </div><!-- /shell -->

</div><!-- /#e-shell -->

<!-- Toast -->
<div id="e-toast">
  <span class="e-toast-msg"></span>
</div>

<!-- App JS -->
<script src="/assets/js/ersus.js"></script>

<script>
  // Mobile sidebar toggle visibility
  if (window.innerWidth <= 768) {
    document.getElementById('btn-sidebar-toggle').style.display = 'block';
  }
  window.addEventListener('resize', () => {
    document.getElementById('btn-sidebar-toggle').style.display =
      window.innerWidth <= 768 ? 'block' : 'none';
  });
</script>

</body>
</html>
