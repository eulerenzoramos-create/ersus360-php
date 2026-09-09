<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ERSUS360 — Secretaria Municipal de Saúde</title>
  <meta name="description" content="Sistema de gestão em saúde municipal — Apuí/AM">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

  <!-- Bootstrap 5.3 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">

  <!-- App CSS -->
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<!-- ── Login overlay ─────────────────────────────────── -->
<div id="e-login" class="e-login-wrap" style="display:none">
  <div class="e-login-card">
    <div class="e-login-logo">ERSUS<span>360</span></div>
    <div class="e-login-sub">Secretaria Municipal de Saúde · Apuí/AM</div>

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
        Entrar
      </button>
    </form>

    <p style="text-align:center;font-size:11px;color:var(--muted);margin-top:20px">
      ERSUS360 v2.0 · PHP 8.4 · Uso restrito à equipe autorizada
    </p>
  </div>
</div>

<!-- ── App shell ──────────────────────────────────────── -->
<div id="e-shell">

  <!-- Topbar -->
  <header class="e-topbar">
    <a href="/" data-route="/" class="e-logo">ERSUS<span>360</span></a>
    <div class="e-topbar-sep"></div>
    <span class="e-muni" id="tb-muni">Apuí/AM</span>

    <div class="e-topbar-right">
      <!-- Alertas -->
      <button class="e-alert-btn" data-route="/alertas" title="Alertas" onclick="router.go('/alertas')">
        🔔
        <span class="e-alert-badge" id="tb-alert-dot" style="display:none"></span>
      </button>

      <!-- Usuário -->
      <button class="e-user-btn" id="btn-user-menu">
        <div class="e-user-avatar" id="tb-user-avatar">U</div>
        <span id="tb-user-name">Usuário</span>
        <span style="color:#64748b;font-size:10px">▾</span>
      </button>

      <!-- Logout -->
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
          <div class="e-sidebar-score-label">Score<br>ERSUS 360</div>
        </div>
      </div>

      <!-- Dashboard -->
      <div class="e-nav-group">
        <button class="e-nav-item" data-route="/">
          <span class="e-nav-icon">🏠</span> Início
        </button>
      </div>

      <!-- Indicadores -->
      <div class="e-nav-group">
        <span class="e-nav-label">Desempenho</span>
        <button class="e-nav-item" data-route="/indicadores">
          <span class="e-nav-icon">📊</span> Mapa de Desempenho
        </button>
        <button class="e-nav-item" data-route="/alertas">
          <span class="e-nav-icon">🔔</span> Alertas
          <span class="e-nav-badge" id="sb-alert-count" style="display:none">0</span>
        </button>
      </div>

      <!-- APS -->
      <div class="e-nav-group">
        <span class="e-nav-label">Atenção Primária</span>
        <button class="e-nav-item" data-route="/aps">
          <span class="e-nav-icon">🏥</span> Cofinanciamento APS
        </button>
        <button class="e-nav-item" style="opacity:.45;cursor:default" title="Em breve">
          <span class="e-nav-icon">🎯</span> Sprint ÓTIMO
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.45;cursor:default" title="Em breve">
          <span class="e-nav-icon">❤️</span> Saúde Brasil 360
          <span class="e-nav-soon">em breve</span>
        </button>
      </div>

      <!-- FNS & Financeiro -->
      <div class="e-nav-group">
        <span class="e-nav-label">FNS & Financeiro</span>
        <button class="e-nav-item" data-route="/fns">
          <span class="e-nav-icon">💰</span> Transferências FNS
        </button>
        <button class="e-nav-item" data-route="/fns/emendas">
          <span class="e-nav-icon">🏛️</span> Emendas Parlamentares
        </button>
        <button class="e-nav-item" data-route="/fns/portarias">
          <span class="e-nav-icon">📋</span> Portarias DOU
        </button>
        <button class="e-nav-item" style="opacity:.45;cursor:default" title="Em breve">
          <span class="e-nav-icon">📑</span> SIOPS / SICONFI
          <span class="e-nav-soon">em breve</span>
        </button>
      </div>

      <!-- Gestão de Pessoas -->
      <div class="e-nav-group">
        <span class="e-nav-label">Gestão de Pessoas</span>
        <button class="e-nav-item" data-route="/folha">
          <span class="e-nav-icon">👥</span> Folha de Presença
        </button>
      </div>

      <!-- CNES -->
      <div class="e-nav-group">
        <span class="e-nav-label">Estabelecimentos</span>
        <button class="e-nav-item" data-route="/cnes">
          <span class="e-nav-icon">🏢</span> CNES
        </button>
      </div>

      <!-- Planejamento -->
      <div class="e-nav-group">
        <span class="e-nav-label">Planejamento</span>
        <button class="e-nav-item" style="opacity:.45;cursor:default">
          <span class="e-nav-icon">📅</span> Plano Municipal
          <span class="e-nav-soon">em breve</span>
        </button>
        <button class="e-nav-item" style="opacity:.45;cursor:default">
          <span class="e-nav-icon">📝</span> RAG / Prestação
          <span class="e-nav-soon">em breve</span>
        </button>
      </div>

      <!-- Admin -->
      <div class="e-nav-group">
        <span class="e-nav-label">Administração</span>
        <button class="e-nav-item" data-route="/admin/usuarios">
          <span class="e-nav-icon">👤</span> Usuários & Perfis
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

<!-- Bootstrap JS (bundle) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>

<!-- App JS -->
<script src="/assets/js/ersus.js"></script>

</body>
</html>
