<?php
echo "<div id='viewModal{$row['id']}' class='fixed inset-0 bg-slate-900/80 backdrop-blur-sm hidden z-[70] transition-all duration-300 ease-in-out' onclick='hideViewModal(\"{$row['id']}\")'>"; 
echo "<div class='flex items-center justify-center min-h-screen px-4 py-8'>";
echo "<div class='modal-container relative w-full max-w-lg mx-auto bg-white dark:bg-slate-900 rounded-2xl shadow-2xl overflow-hidden transform transition-all opacity-0 scale-95 border border-slate-200 dark:border-slate-800' 
            data-modal-id='{$row['id']}' 
            onclick='event.stopPropagation();'>";

// Close button
echo "<button type='button' onclick='hideViewModal(\"{$row['id']}\")' class='absolute top-4 right-4 z-10 w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 focus:outline-none transition-colors' aria-label='Close'>";
echo "<i class='fas fa-times text-sm'></i>";
echo "</button>";

// Header with gradient background
echo "<div class='px-6 py-6 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50'>";
echo "<div class='flex items-center space-x-3'>";
echo "<div class='w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400'><i class='fas fa-info-circle'></i></div>";
echo "<div>";
echo "<h3 class='text-lg font-bold text-slate-900 dark:text-white'>Domain Details</h3>";
echo "<p class='text-slate-500 dark:text-slate-400 text-xs mt-1 font-mono'>" . htmlspecialchars($row['domain'] ?? 'N/A') . "</p>";
echo "</div>";
echo "</div>";
echo "</div>";

// Domain details content
echo "<div class='px-6 py-6 max-h-[70vh] overflow-y-auto custom-scrollbar'>";

// Status Badges Row
$is_expired = ($row['license_type'] !== 'lifetime' && !empty($row['expiry_date']) && $row['expiry_date'] < date('Y-m-d'));
echo "<div class='flex flex-wrap gap-2 mb-6'>";

if ($row['active'] == 1 && !$is_expired) {
    echo "<span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 uppercase tracking-wider'><i class='fas fa-check-circle mr-1.5'></i>Active</span>";
} elseif ($is_expired) {
    echo "<span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 uppercase tracking-wider'><i class='fas fa-exclamation-circle mr-1.5'></i>Expired</span>";
} else {
    echo "<span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-400 uppercase tracking-wider'><i class='fas fa-times-circle mr-1.5'></i>Inactive</span>";
}

if ($row['delete'] == 'yes') {
    echo "<span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400 uppercase tracking-wider'><i class='fas fa-trash-alt mr-1.5'></i>Deletion</span>";
}

echo "</div>";

// Details Grid
echo "<div class='space-y-4'>";

// Name and Email Row
echo "<div class='grid grid-cols-1 sm:grid-cols-2 gap-4'>";

// Client Name
echo "<div class='space-y-1'>";
echo "<label class='text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-500'>Client Name</label>";
echo "<div class='p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-100 dark:border-slate-800 text-slate-900 dark:text-white text-sm font-medium break-all'>" . htmlspecialchars($row['name'] ?? 'N/A') . "</div>";
echo "</div>";

// Client Email
echo "<div class='space-y-1'>";
echo "<label class='text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-500'>Email</label>";
echo "<div class='p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-100 dark:border-slate-800 text-indigo-600 dark:text-indigo-400 text-sm font-medium break-all'><a href='mailto:" . htmlspecialchars($row['email'] ?? '') . "' class='hover:underline'>" . htmlspecialchars($row['email'] ?? 'N/A') . "</a></div>";
echo "</div>";

echo "</div>"; 

// License Info Row
echo "<div class='grid grid-cols-1 sm:grid-cols-2 gap-4'>";

// License Type
echo "<div class='space-y-1'>";
echo "<label class='text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-500'>License Type</label>";
echo "<div class='p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-100 dark:border-slate-800 text-slate-900 dark:text-white text-sm font-medium capitalize'>" . htmlspecialchars($row['license_type'] ?? 'N/A') . "</div>";
echo "</div>";

// Expiry Date
echo "<div class='space-y-1'>";
echo "<label class='text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-500'>Expiry Date</label>";
echo "<div class='p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-100 dark:border-slate-800 text-slate-900 dark:text-white text-sm font-medium'>" . ($row['license_type'] === 'lifetime' ? '<span class="text-emerald-600 dark:text-emerald-400">Never</span>' : htmlspecialchars($row['expiry_date'] ?? 'N/A')) . "</div>";
echo "</div>";

echo "</div>"; 

// Internal Notes
echo "<div class='space-y-1'>";
echo "<label class='text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-500'>Internal Notes</label>";
echo "<div class='p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-100 dark:border-slate-800 text-slate-700 dark:text-slate-300 text-sm whitespace-pre-wrap break-words min-h-[80px]'>" . 
    (!empty($row['message']) ? nl2br(htmlspecialchars($row['message'])) : '<span class="text-slate-400 italic">No notes available</span>') . 
    "</div>";
echo "</div>";

echo "</div>"; // End space-y-4
echo "</div>"; // End content section

// Footer
echo "<div class='bg-slate-50 dark:bg-slate-950/50 px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex justify-end space-x-3'>";

echo "<a href='edit-domain?id={$row['id']}' class='px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-700 dark:text-slate-300 text-sm font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition'>Edit</a>";
echo "<button type='button' onclick='hideViewModal(\"{$row['id']}\")' class='px-4 py-2 bg-slate-900 dark:bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-slate-800 dark:hover:bg-indigo-500 transition'>";
echo "Close";
echo "</button>";

echo "</div>";

echo "</div>"; // End modal container
echo "</div>"; // End flex container
echo "</div>"; // End modal overlay
?>
