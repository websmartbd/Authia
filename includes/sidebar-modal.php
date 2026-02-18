<?php
// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Mobile Header -->
<?php
$page_titles = [
    'dashboard.php' => 'Dashboard',
    'domains.php' => 'Active Domains',
    'add-domain.php' => 'Add Domain',
    'edit-domain.php' => 'Edit Domain',
    'expires.php' => 'Expired Domains',
    'delete.php' => 'Deletion Queue',
    'mail.php' => 'SMTP Configuration',
    'change-password.php' => 'Security Settings'
];
$mobile_title = $page_titles[$current_page] ?? 'Authia Admin';
?>
<div class="md:hidden fixed top-0 inset-x-0 z-40 h-16 bg-white dark:bg-slate-950/80 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-4">
    <img src="https://authia.hs.vc/security.png" alt="Authia" class="w-8 h-8">
    
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
        <span class="font-bold text-lg text-slate-900 dark:text-white truncate px-12"><?php echo $mobile_title; ?></span>
    </div>

    <button id="open-sidebar" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:text-indigo-600 transition-colors z-10">
        <i class="fas fa-bars"></i>
    </button>
</div>

<!-- Responsive Sidebar -->
<div id="sidebar" class="fixed md:relative inset-y-0 left-0 z-50 w-72 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col h-full shadow-2xl md:shadow-none flex-shrink-0">
  
  <!-- Logo Area -->
  <div class="h-16 flex items-center px-6 border-b border-slate-200 dark:border-slate-800 bg-white/50 dark:bg-slate-950/50 backdrop-blur-sm">
    <div class="flex items-center space-x-3">
        <img src="https://authia.hs.vc/security.png" alt="Authia" class="w-8 h-8">
        <span class="text-lg font-bold tracking-tight text-slate-900 dark:text-white font-sans">Authia</span>
    </div>
    <button id="close-sidebar" class="md:hidden ml-auto text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition focus:outline-none">
      <i class="fas fa-times text-xl"></i>
    </button>
  </div>

  <!-- Navigation Links -->
  <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-1">
    <a href="dashboard" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 group <?php echo ($current_page == 'dashboard.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/20 ring-1 ring-indigo-500' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'; ?>">
      <i class="fas fa-chart-pie w-6 <?php echo ($current_page == 'dashboard.php') ? 'text-indigo-200' : 'text-slate-400 dark:text-slate-500 group-hover:text-indigo-500 dark:group-hover:text-indigo-400'; ?> transition-colors"></i> 
      <span class="font-medium text-sm">Dashboard</span>
    </a>
    
    <div class="pt-6 pb-2 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Management</div>

    <a href="domains" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 group <?php echo ($current_page == 'domains.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/20 ring-1 ring-indigo-500' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'; ?>">
      <i class="fas fa-globe w-6 <?php echo ($current_page == 'domains.php') ? 'text-indigo-200' : 'text-slate-400 dark:text-slate-500 group-hover:text-indigo-500 dark:group-hover:text-indigo-400'; ?> transition-colors"></i>
      <span class="font-medium text-sm">active Domains</span>
    </a>
    
    <a href="add-domain" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 group <?php echo ($current_page == 'add-domain.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/20 ring-1 ring-indigo-500' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'; ?>">
      <i class="fas fa-plus-circle w-6 <?php echo ($current_page == 'add-domain.php') ? 'text-indigo-200' : 'text-slate-400 dark:text-slate-500 group-hover:text-indigo-500 dark:group-hover:text-indigo-400'; ?> transition-colors"></i> 
      <span class="font-medium text-sm">Add Domain</span>
    </a>

    <div class="pt-6 pb-2 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Monitoring</div>

    <a href="expires" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 group <?php echo ($current_page == 'expires.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/20 ring-1 ring-indigo-500' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'; ?>">
      <i class="fas fa-clock w-6 <?php echo ($current_page == 'expires.php') ? 'text-indigo-200' : 'text-slate-400 dark:text-slate-500 group-hover:text-indigo-500 dark:group-hover:text-indigo-400'; ?> transition-colors"></i> 
      <span class="font-medium text-sm">Expired</span>
    </a>
    
    <a href="delete" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 group <?php echo ($current_page == 'delete.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/20 ring-1 ring-indigo-500' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'; ?>">
      <i class="fas fa-trash-can w-6 <?php echo ($current_page == 'delete.php') ? 'text-indigo-200' : 'text-slate-400 dark:text-slate-500 group-hover:text-indigo-500 dark:group-hover:text-indigo-400'; ?> transition-colors"></i> 
      <span class="font-medium text-sm">Deletion Queue</span>
    </a>

    <div class="pt-6 pb-2 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">System</div>

    <!-- Settings Menu Item -->
    <div class="space-y-1">
      <button onclick="toggleSettingsMenu()" class="w-full flex items-center justify-between px-4 py-3 rounded-lg transition-all duration-200 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white group focus:outline-none">
        <div class="flex items-center">
          <i class="fas fa-gear w-6 text-slate-400 dark:text-slate-500 group-hover:text-indigo-500 dark:group-hover:text-indigo-400 transition-colors"></i> 
          <span class="font-medium text-sm">Configuration</span>
        </div>
        <i id="settings-arrow" class="fas fa-chevron-down text-xs transition-transform duration-200 <?php echo ($current_page == 'change-password.php' || $current_page == 'mail.php') ? 'rotate-180' : ''; ?>"></i>
      </button>

      <div id="settings-submenu" class="pl-4 pr-2 space-y-1 overflow-hidden transition-all duration-300 <?php echo ($current_page == 'change-password.php' || $current_page == 'mail.php') ? 'max-h-40 opacity-100' : 'max-h-0 opacity-0'; ?>">
        <div class="border-l border-slate-200 dark:border-slate-800 ml-5 pl-4 space-y-1 py-1">
            <a href="mail" class="block py-2 text-sm transition-colors duration-200 <?php echo ($current_page == 'mail.php') ? 'text-indigo-600 dark:text-white font-medium' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'; ?>">
            SMTP Settings
            </a>
            <a href="change-password" class="block py-2 text-sm transition-colors duration-200 <?php echo ($current_page == 'change-password.php') ? 'text-indigo-600 dark:text-white font-medium' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'; ?>">
            Change Password
            </a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Bottom Actions -->
  <div class="p-4 border-t border-slate-200 dark:border-slate-800 bg-white/50 dark:bg-slate-950/50 backdrop-blur-sm">
    <div class="grid grid-cols-3 gap-2">
        <!-- Theme Toggle -->
        <button id="theme-toggle-btn" class="flex items-center justify-center py-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:border-slate-300 dark:hover:border-slate-700 transition group" title="Toggle Theme">
            <span class="dark:hidden"><i class="fas fa-sun text-amber-500"></i></span>
            <span class="hidden dark:block"><i class="fas fa-moon text-indigo-400"></i></span>
        </button>

        <!-- Docs -->
        <a href="docs" target="_blank" class="flex items-center justify-center py-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:border-slate-300 dark:hover:border-slate-700 transition" title="Documentation">
            <i class="fas fa-book-open"></i>
        </a>

        <!-- Logout -->
        <a href="logout" class="flex items-center justify-center py-2.5 rounded-lg bg-red-500/10 border border-transparent hover:border-red-500/50 text-red-500 hover:bg-red-500 hover:text-white transition" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
  </div>
</div>

<!-- Mobile Backdrop -->
<div id="sidebar-backdrop" class="fixed inset-0 bg-slate-950/80 z-40 hidden transition-opacity duration-300 opacity-0 backdrop-blur-sm"></div>

<script>
  // Theme Toggle Logic
  const themeBtn = document.getElementById('theme-toggle-btn');
  
  function updateThemeIcon() {
      // Icon updating handled by CSS classes (dark:hidden etc)
  }

  function toggleTheme() {
      if (document.documentElement.classList.contains('dark')) {
          document.documentElement.classList.remove('dark');
          localStorage.setItem('theme', 'light');
      } else {
          document.documentElement.classList.add('dark');
          localStorage.setItem('theme', 'dark');
      }
      updateThemeIcon();
  }

  themeBtn?.addEventListener('click', toggleTheme);

  // Sidebar Logic
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebar-backdrop');
  const closeBtn = document.getElementById('close-sidebar');
  const openBtn = document.getElementById('open-sidebar'); // Might be in parent file

  function openSidebar() {
    sidebar.classList.remove('-translate-x-full');
    backdrop.classList.remove('hidden');
    // Force reflow
    void sidebar.offsetWidth; 
    backdrop.classList.remove('opacity-0');
  }

  function closeSidebar() {
    sidebar.classList.add('-translate-x-full');
    backdrop.classList.add('opacity-0');
    setTimeout(() => {
        backdrop.classList.add('hidden');
    }, 300);
  }

  // Event Listeners
  if(openBtn) openBtn.addEventListener('click', openSidebar);
  if(closeBtn) closeBtn.addEventListener('click', closeSidebar);
  if(backdrop) backdrop.addEventListener('click', closeSidebar);

  // Settings Menu Logic
  window.toggleSettingsMenu = function() {
    const submenu = document.getElementById('settings-submenu');
    const arrow = document.getElementById('settings-arrow');
    
    if (submenu.classList.contains('max-h-0')) {
        submenu.classList.remove('max-h-0', 'opacity-0');
        submenu.classList.add('max-h-40', 'opacity-100');
        arrow.classList.add('rotate-180');
    } else {
        submenu.classList.add('max-h-0', 'opacity-0');
        submenu.classList.remove('max-h-40', 'opacity-100');
        arrow.classList.remove('rotate-180');
    }
  }
</script>