<?php
echo "<div id='viewModal{$row['id']}' class='fixed inset-0 bg-gray-900 bg-opacity-0 hidden z-50 transition-all duration-300 ease-in-out'>"; 
echo "<div class='flex items-center justify-center min-h-screen px-2 sm:px-4 pt-4 pb-20 text-center sm:p-0 w-full'>";
echo "<div class='modal-container transform transition-all sm:align-middle w-full sm:max-w-lg mx-auto opacity-0 sm:scale-95' 
            data-modal-id='{$row['id']}' 
            onclick='event.stopPropagation();'>";

// Modal content with modern design
echo "<div class='relative bg-white rounded-2xl shadow-2xl overflow-hidden transform transition-all sm:max-w-lg sm:w-full'>";

// Close button - positioned absolutely
echo "<button type='button' onclick='hideViewModal(\"{$row['id']}\")' class='absolute top-4 right-4 z-10 text-white hover:text-gray-200 focus:outline-none transition ease-in-out duration-150' aria-label='Close'>";
echo "<svg class='h-6 w-6' stroke='currentColor' fill='none' viewBox='0 0 24 24'>";
echo "<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 18L18 6M6 6l12 12'></path>";
echo "</svg>";
echo "</button>";

// Header with gradient background
echo "<div class='bg-gradient-to-r from-indigo-600 to-purple-600 px-4 sm:px-6 py-4 sm:py-6'>";
echo "<h3 class='text-lg sm:text-xl font-bold text-white'>Domain Details</h3>";
echo "<p class='text-indigo-100 text-xs sm:text-sm mt-1 break-all'>" . htmlspecialchars($row['domain'] ?? 'N/A') . "</p>";
echo "</div>";

// Domain details content
echo "<div class='px-3 sm:px-6 py-4 sm:py-6 max-h-[70vh] overflow-y-auto'>";

// Status Badges Row
$is_expired = ($row['license_type'] !== 'lifetime' && !empty($row['expiry_date']) && $row['expiry_date'] < date('Y-m-d'));
$status_badge = '';
if ($row['active'] == 1) {
    if ($is_expired) {
        $status_badge = "<span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800'><i class='fas fa-exclamation-circle mr-1.5'></i>Expired Access</span>";
    } else {
        $status_badge = "<span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800'><i class='fas fa-check-circle mr-1.5'></i>Active</span>";
    }
} else {
    $status_badge = "<span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800'><i class='fas fa-times-circle mr-1.5'></i>Inactive</span>";
}

echo "<div class='flex gap-2 mb-6'>";
echo $status_badge;
if ($row['delete'] == 'yes') {
    echo "<span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-800'><i class='fas fa-trash-alt mr-1.5'></i>Pending Deletion</span>";
}
echo "</div>";

// Details Grid
echo "<div class='space-y-3 sm:space-y-4'>";

// Name and Email Row
echo "<div class='grid grid-cols-2 gap-2 sm:gap-4'>";

// Client Name
echo "<div class='bg-gray-50 rounded-lg p-2 sm:p-4 border border-gray-100'>";
echo "<div class='text-[10px] sm:text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1'>Client Name</div>";
echo "<div class='text-sm sm:text-base text-gray-900 font-medium break-all'>" . htmlspecialchars($row['name'] ?? 'N/A') . "</div>";
echo "</div>";

// Client Email
echo "<div class='bg-gray-50 rounded-lg p-2 sm:p-4 border border-gray-100'>";
echo "<div class='text-[10px] sm:text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1'>Email</div>";
echo "<div class='text-sm sm:text-base text-indigo-600 font-medium break-all'><a href='mailto:" . htmlspecialchars($row['email'] ?? '') . "'>" . htmlspecialchars($row['email'] ?? 'N/A') . "</a></div>";
echo "</div>";

echo "</div>"; // End name/email grid

// License Info Row
echo "<div class='grid grid-cols-2 gap-2 sm:gap-4'>";

// License Type
echo "<div class='bg-gray-50 rounded-lg p-2 sm:p-4 border border-gray-100'>";
echo "<div class='text-[10px] sm:text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1'>License</div>";
echo "<div class='text-sm sm:text-base text-gray-900 font-medium capitalize'>" . htmlspecialchars($row['license_type'] ?? 'N/A') . "</div>";
echo "</div>";

// Expiry Date
echo "<div class='bg-gray-50 rounded-lg p-2 sm:p-4 border border-gray-100'>";
echo "<div class='text-[10px] sm:text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1'>Expiry</div>";
echo "<div class='text-sm sm:text-base text-gray-900 font-medium'>" . ($row['license_type'] === 'lifetime' ? '<span class="text-green-600">Never</span>' : htmlspecialchars($row['expiry_date'] ?? 'N/A')) . "</div>";
echo "</div>";

echo "</div>"; // End license grid

// Internal Notes
echo "<div class='bg-gray-50 rounded-lg p-3 sm:p-4 border border-gray-100'>";
echo "<div class='text-xs sm:text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2'>Internal Notes</div>";
echo "<div class='text-xs sm:text-sm text-gray-700 whitespace-pre-wrap break-words max-h-32 overflow-y-auto'>" . 
    (!empty($row['message']) ? nl2br(htmlspecialchars($row['message'])) : '<span class="text-gray-400 italic">No notes available</span>') . 
    "</div>";
echo "</div>";

echo "</div>"; // End space-y-4
echo "</div>"; // End content section

// Footer
echo "<div class='bg-gray-50 px-3 sm:px-6 py-3 sm:py-4 border-t border-gray-200 flex justify-end'>";
echo "<button type='button' onclick='hideViewModal(\"{$row['id']}\")' class='inline-flex items-center justify-center px-4 sm:px-6 py-2 sm:py-2.5 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200'>";
echo "<i class='fas fa-times mr-2'></i>Close";
echo "</button>";
echo "</div>";

echo "</div>"; // End modal content
echo "</div>"; // End modal container
echo "</div>"; // End flex container
echo "</div>"; // End modal overlay


?>
