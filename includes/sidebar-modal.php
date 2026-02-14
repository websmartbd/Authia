<?php
// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Responsive Sidebar -->
<div id="sidebar" class="fixed md:relative inset-y-0 left-0 z-30 w-64 p-4 bg-gray-800 text-white transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out md:shadow-xl md:block md:h-auto h-full md:w-64 absolute md:static">
  <div class="flex items-center justify-between mb-8">
    <div class="flex items-center space-x-3">
      <svg class="h-8 w-8 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
      </svg>
      <span class="text-xl font-bold tracking-tight">Authenticator</span>
    </div>
    <!-- Close button for mobile -->
    <button id="close-sidebar" class="md:hidden text-gray-400 hover:text-white focus:outline-none transition duration-150">
      <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </div>

  <!-- Navigation Links -->
  <nav class="space-y-3">
    <a href="dashboard" class="flex items-center px-4 py-3 rounded-lg transition duration-200 hover:bg-gray-700 <?php echo ($current_page == 'dashboard.php') ? 'bg-gray-700 text-white' : 'text-gray-300'; ?>">
      <i class="fas fa-home text-lg mr-3"></i> 
      <span>Dashboard</span>
    </a>
    <a href="domains" class="flex items-center px-4 py-3 rounded-lg transition duration-200 hover:bg-gray-700 <?php echo ($current_page == 'domains.php') ? 'bg-gray-700 text-white' : 'text-gray-300'; ?>">
      <i class="fas fa-globe mr-2"></i>All Domains
    </a>
    <a href="expires" class="flex items-center px-4 py-3 rounded-lg transition duration-200 hover:bg-gray-700 <?php echo ($current_page == 'expires.php') ? 'bg-gray-700 text-white' : 'text-gray-300'; ?>">
      <i class="fas fa-calendar-times mr-2 text-lg mr-3"></i> 
      <span>Expired Domains</span>
    </a>
    <a href="delete" class="flex items-center px-4 py-3 rounded-lg transition duration-200 hover:bg-gray-700 <?php echo ($current_page == 'delete.php') ? 'bg-gray-700 text-white' : 'text-gray-300'; ?>">
      <i class="fas fa-trash-restore mr-2 text-lg mr-3"></i> 
      <span>Flagged Delete</span>
    </a>
    <a href="add-domain" class="flex items-center px-4 py-3 rounded-lg transition duration-200 hover:bg-gray-700 <?php echo ($current_page == 'add-domain.php') ? 'bg-gray-700 text-white' : 'text-gray-300'; ?>">
      <i class="fas fa-plus text-lg mr-3"></i> 
      <span>Add New Domain</span>
    </a>

    <!-- Settings Menu Item with Sub-menu -->
    <div class="nav-item <?php echo ($current_page == 'api-generate.php' || $current_page == 'change-password.php') ? 'bg-gray-700 rounded-lg' : ''; ?>">
      <!-- Clickable header for Settings -->
      <div class="clickable-menu-item flex items-center justify-between px-4 py-3 rounded-lg transition duration-200 hover:bg-gray-700 text-gray-300" id="settings-menu-toggle">
        <div class="flex items-center">
          <i class="fas fa-cog text-lg mr-3"></i> 
          <span>Settings</span>
        </div>
        <!-- Arrow icon to indicate dropdown state -->
        <svg class="w-4 h-4 transform transition-transform duration-200 text-gray-400 <?php echo ($current_page == 'api-generate.php' || $current_page == 'change-password.php') ? 'rotate-180' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
      </div>

      <!-- Sub-menu for Settings -->
      <div class="sub-menu mt-1 ml-2 overflow-hidden transition-all duration-200 <?php echo ($current_page == 'change-password.php' || $current_page == 'mail.php') ? 'block' : 'hidden'; ?>">
        <a href="mail" class="flex items-center py-2 px-4 rounded-md transition duration-200 hover:bg-gray-600 <?php echo ($current_page == 'mail.php') ? 'active text-white bg-gray-600' : 'text-gray-400'; ?>">
          <i class="fas fa-envelope mr-2"></i> Email Settings
        </a>
        <a href="change-password" class="flex items-center py-2 px-4 rounded-md transition duration-200 hover:bg-gray-600 <?php echo ($current_page == 'change-password.php') ? 'active text-white bg-gray-600' : 'text-gray-400'; ?>">
          <i class="fas fa-lock mr-2"></i> Change Password
        </a>
        <a href="logout" class="flex items-center py-2 px-4 rounded-md transition duration-200 hover:bg-gray-600 text-gray-400">
          <i class="fas fa-sign-out-alt mr-2"></i> Logout
        </a>
      </div>
    </div>
  </nav>

    <!-- Documentation link at the bottom -->
    <div class="absolute bottom-6 left-0 w-full px-4">
      <a href="doc" class="flex items-center justify-center w-full px-4 py-2.5 border border-transparent text-sm font-medium rounded-lg text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150">
        <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
        </svg>
        Documentation
      </a>
    </div>
</div>

    <!-- Main content area -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Header for mobile -->
      <header class="flex items-center justify-between md:hidden bg-white shadow-sm p-4">
        <div class="flex items-center">
          <?php if (isset($page_icon)): ?>
            <?php echo $page_icon; ?>
          <?php else: ?>
            <svg class="h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9' />
            </svg>
          <?php endif; ?>
          <h1 class="ml-3 text-xl font-bold text-gray-900"><?php echo isset($page_title) ? $page_title : 'Authenticator'; ?></h1>
        </div>
        <button id="open-sidebar" class="text-gray-500 hover:text-gray-600 focus:outline-none">
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4 6h16M4 12h16M4 18h16' />
          </svg>
        </button>
      </header>

<!-- Mobile Backdrop -->
<div id="sidebar-backdrop" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-20 hidden md:hidden transition-opacity duration-300 opacity-0" aria-hidden="true"></div>

<!-- Sidebar JavaScript -->
<script>
  // Sidebar toggle functionality
  const sidebar = document.getElementById('sidebar');
  const openSidebarBtn = document.getElementById('open-sidebar');
  const closeSidebarBtn = document.getElementById('close-sidebar');
  const backdrop = document.getElementById('sidebar-backdrop');
  const settingsMenuToggle = document.getElementById('settings-menu-toggle');
  const settingsSubMenu = document.querySelector('#settings-menu-toggle + .sub-menu');

  function openSidebar() {
    sidebar.classList.remove('-translate-x-full');
    backdrop.classList.remove('hidden');
    // small delay to allow display:block to apply before opacity transition
    setTimeout(() => {
        backdrop.classList.remove('opacity-0');
    }, 10);
  }

  function closeSidebar() {
    sidebar.classList.add('-translate-x-full');
    backdrop.classList.add('opacity-0');
    setTimeout(() => {
        backdrop.classList.add('hidden');
    }, 300);
  }

  if (openSidebarBtn) {
      openSidebarBtn.addEventListener('click', openSidebar);
  }

  if (closeSidebarBtn) {
      closeSidebarBtn.addEventListener('click', closeSidebar);
  }
  
  if (backdrop) {
      backdrop.addEventListener('click', closeSidebar);
  }

  // Toggle settings sub-menu visibility on click
  if (settingsMenuToggle && settingsSubMenu) {
    settingsMenuToggle.addEventListener('click', () => {
      settingsSubMenu.classList.toggle('hidden');
      // Rotate arrow icon
      const arrowIcon = settingsMenuToggle.querySelector('svg');
      if (arrowIcon) {
        arrowIcon.classList.toggle('rotate-180');
      }
    });
  }
</script> 