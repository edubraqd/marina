<?php

$area_nav_active = $area_nav_active ?? 'dashboard';
$needsOnboarding = $area_nav_onboarding ?? false;
$navItems = [
    'dashboard' => ['label' => 'Visão geral', 'icon' => 'ti-layout', 'href' => '/area'],
    'planos'    => ['label' => 'Planos & arquivos', 'icon' => 'ti-folder', 'href' => '/area-planos'],
    'treinos'   => ['label' => 'Treinos', 'icon' => 'ti-control-play', 'href' => '/area-treinos'],
    'config'    => ['label' => 'Configurações', 'icon' => 'ti-settings', 'href' => '/area-config'],
];
$isAdmin = isset($current_user) && ($current_user['role'] ?? 'student') === 'admin';
if ($isAdmin) {
    $navItems = [
        'admin'        => ['label' => 'Painel/Alunos', 'icon' => 'ti-dashboard', 'href' => '/area-admin'],
        'admin_treinos'=> ['label' => 'Treinos por aluno', 'icon' => 'ti-panel', 'href' => '/area-admin-treinos'],
        'admin_links'  => ['label' => 'Links (vídeos/recursos)', 'icon' => 'ti-link', 'href' => '/area-admin-links'],
        'admin_planos' => ['label' => 'Planos (valores)', 'icon' => 'ti-money', 'href' => '/area-admin-planos'],
        'admin_reprocess' => ['label' => 'Reprocessar pagamento', 'icon' => 'ti-reload', 'href' => '/mp-reprocess'],
    ];
} elseif ($needsOnboarding) {
    $navItems = [
        'dashboard' => ['label' => 'Visão geral', 'icon' => 'ti-layout', 'href' => '/area'],
        'form'      => ['label' => 'Formulário inicial', 'icon' => 'ti-write', 'href' => '/formulario-inicial'],
        'logout'    => ['label' => 'Sair', 'icon' => 'ti-shift-left', 'href' => '/area-logout'],
    ];
}
?>
<nav class="area-nav">
  <ul>
    <?php foreach ($navItems as $slug => $item): ?>
      <li class="<?php echo $area_nav_active === $slug ? 'is-active' : ''?>">
        <a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8')?>">
          <i class="<?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8')?>"></i>
          <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8')?></span>
        </a>
      </li>
    <?php endforeach;?>
  </ul>
</nav>
