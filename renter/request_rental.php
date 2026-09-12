<?php
/**
 * Online Rental Management System (ORMS)
 * Rental Request Submission Page
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 6 (Step 6), Section 5 (Rules 5, 7) & Section 7
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/RentalRequest.php';
require_once __DIR__ . '/../classes/Renter.php';
require_once __DIR__ . '/../classes/exceptions/InvalidDateRangeException.php';
require_once __DIR__ . '/../classes/exceptions/ProductUnavailableException.php';
require_once __DIR__ . '/../classes/exceptions/ORMSException.php';

// Ensure user is logged in
if (!is_logged_in()) {
    $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    header('Location: ' . base_url('auth/login.php?redirect=' . urlencode($currentUrl)));
    exit;
}

// Ensure active role is Renter
if (current_role() !== 'Renter') {
    if (in_array('Renter', user_roles(), true)) {
        header('Location: ' . base_url('auth/switch_role.php?role=Renter'));
        exit;
    }
    set_flash('error', 'You must register or hold the Renter role to request rentals.');
    header('Location: ' . base_url('index.php'));
    exit;
}

$productId = (int) ($_GET['product_id'] ?? $_GET['id'] ?? $_POST['product_id'] ?? 0);
if ($productId <= 0) {
    set_flash('error', 'Invalid product specified.');
    header('Location: ' . base_url('renter/search.php'));
    exit;
}

$product = Product::findById($productId);
if (!$product) {
    set_flash('error', 'Product not found or has been removed.');
    header('Location: ' . base_url('renter/search.php'));
    exit;
}

$renterId = (int) current_user_id();

// Disallow renting own product
if ($product->getOwnerID() === $renterId) {
    set_flash('error', 'You cannot rent your own listed product.');
    header('Location: ' . base_url('renter/product_details.php?id=' . $productId));
    exit;
}

// Disallow renting if status is not Available
if ($product->getAvailStatus() !== 'Available') {
    set_flash('error', 'This product is currently ' . htmlspecialchars($product->getAvailStatus()) . ' and unavailable for booking.');
    header('Location: ' . base_url('renter/product_details.php?id=' . $productId));
    exit;
}

$errors = [];
$startDate = $_POST['start_date'] ?? date('Y-m-d');
$endDate = $_POST['end_date'] ?? date('Y-m-d', strtotime('+3 days'));
$message = $_POST['message'] ?? '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Security token invalid or expired. Please reload and try again.';
    } else {
        $cleanStartDate = trim($startDate);
        $cleanEndDate = trim($endDate);
        $cleanMessage = trim($message);

        // Basic sanity
        if (empty($cleanStartDate) || empty($cleanEndDate)) {
            $errors[] = 'Both start date and end date are required.';
        } else {
            try {
                $renter = new Renter($renterId);
                // Atomic submission with SELECT ... FOR UPDATE locking
                $rentalReq = $renter->sendRequest($productId, $cleanStartDate, $cleanEndDate, $cleanMessage);

                set_flash('success', "Rental request #{$rentalReq->getRequestID()} submitted successfully! The owner will review your booking.");
                header('Location: ' . base_url('renter/my_rentals.php'));
                exit;

            } catch (InvalidDateRangeException $e) {
                $errors[] = $e->getMessage();
            } catch (ProductUnavailableException $e) {
                $errors[] = $e->getMessage();
            } catch (ORMSException $e) {
                $errors[] = $e->getMessage();
            } catch (Exception $e) {
                $errors[] = 'An unexpected error occurred while processing your request: ' . $e->getMessage();
            }
        }
    }
}

$images = $product->getImages();
$primaryImg = !empty($images) ? $images[0]['image_path'] : 'assets/img/no-image.svg';

$pageTitle = 'Request Rental — ' . htmlspecialchars($product->getTitle());
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-400 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('index.php') ?>" class="hover:text-white">Home</a></li>
            <li><span>/</span></li>
            <li><a href="<?= base_url('renter/search.php') ?>" class="hover:text-white">Catalog</a></li>
            <li><span>/</span></li>
            <li><a href="<?= base_url('renter/product_details.php?id=' . $productId) ?>" class="hover:text-white truncate max-w-[150px]"><?= htmlspecialchars($product->getTitle()) ?></a></li>
            <li><span>/</span></li>
            <li class="text-slate-200 font-semibold">Request Rental</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Book Rental Request</h1>
        <p class="text-sm text-slate-400 mt-1">Select your desired rental duration. No immediate payment is required until the owner approves your request.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-950/60 border border-rose-800/80 text-rose-300 text-sm">
            <div class="font-bold flex items-center space-x-2 mb-1">
                <span>⚠️</span>
                <span>Booking Error:</span>
            </div>
            <ul class="list-disc list-inside space-y-1 text-xs">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Left: Product Summary Card -->
        <div class="lg:col-span-5 space-y-6">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
                <div class="aspect-video w-full rounded-xl overflow-hidden bg-slate-950 border border-slate-800 mb-4">
                    <img src="<?= base_url($primaryImg) ?>" 
                         alt="<?= htmlspecialchars($product->getTitle()) ?>" 
                         class="w-full h-full object-cover"
                         onerror="this.src='<?= base_url('assets/img/no-image.svg') ?>'">
                </div>

                <span class="text-[11px] font-semibold text-blue-400 uppercase tracking-wider block">Product Overview</span>
                <h2 class="text-lg font-bold text-white mt-1"><?= htmlspecialchars($product->getTitle()) ?></h2>
                <div class="flex items-center space-x-2 text-xs text-slate-400 mt-1">
                    <span>📍 <?= htmlspecialchars($product->getLocation()) ?></span>
                    <span>&bull;</span>
                    <span>Condition: <?= htmlspecialchars($product->getCondition()) ?></span>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-800 space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Daily Rental Rate:</span>
                        <span class="font-bold text-white">₹<span id="ratePerDayDisplay"><?= number_format($product->getRentPerDay(), 2) ?></span>/day</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Refundable Deposit:</span>
                        <span class="font-bold text-emerald-400">₹<span id="securityDepositDisplay"><?= number_format($product->getSecurityDeposit(), 2) ?></span></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Lender / Owner:</span>
                        <span class="font-semibold text-slate-200"><?= htmlspecialchars($product->getOwnerName() ?? 'Owner') ?></span>
                    </div>
                </div>

                <!-- Trust Guarantee Box -->
                <div class="mt-5 p-3 rounded-xl bg-slate-950/60 border border-slate-800 text-[11px] text-slate-400 space-y-1.5">
                    <div class="flex items-center space-x-1.5 text-emerald-400 font-medium">
                        <span>🛡️</span>
                        <span>Zero Risk Booking</span>
                    </div>
                    <p>Free cancellation while request status is Pending. Security deposit is 100% refunded after satisfactory return.</p>
                </div>
            </div>
        </div>

        <!-- Right: Booking Form & Real-time Cost Breakdown -->
        <div class="lg:col-span-7 space-y-6">
            <form action="<?= base_url('renter/request_rental.php?product_id=' . $productId) ?>" method="POST" class="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-xl">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= $productId ?>">

                <h3 class="text-base font-bold text-white mb-4 pb-2 border-b border-slate-800 flex items-center space-x-2">
                    <span>📅</span>
                    <span>Rental Schedule & Dates</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label for="start_date" class="block text-xs font-semibold text-slate-300 mb-1.5">
                            Start Date <span class="text-rose-400">*</span>
                        </label>
                        <input type="date" 
                               id="start_date" 
                               name="start_date" 
                               value="<?= htmlspecialchars($startDate) ?>" 
                               min="<?= date('Y-m-d') ?>" 
                               required
                               class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                        <span class="text-[10px] text-slate-500 mt-1 block">Earliest pickup / delivery date</span>
                    </div>

                    <div>
                        <label for="end_date" class="block text-xs font-semibold text-slate-300 mb-1.5">
                            End Date <span class="text-rose-400">*</span>
                        </label>
                        <input type="date" 
                               id="end_date" 
                               name="end_date" 
                               value="<?= htmlspecialchars($endDate) ?>" 
                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>" 
                               required
                               class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                        <span class="text-[10px] text-slate-500 mt-1 block">Scheduled return date</span>
                    </div>
                </div>

                <!-- Message to Owner -->
                <div class="mb-6">
                    <label for="message" class="block text-xs font-semibold text-slate-300 mb-1.5">
                        Message to Owner <span class="text-slate-500 font-normal">(Optional)</span>
                    </label>
                    <textarea id="message" 
                              name="message" 
                              rows="3" 
                              placeholder="Introduce yourself or mention any specific pickup preferences..."
                              class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-700 rounded-xl text-white text-sm placeholder-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"><?= htmlspecialchars($message) ?></textarea>
                </div>

                <!-- Live Dynamic Pricing Card -->
                <div class="bg-gradient-to-br from-slate-950 to-slate-900 border border-indigo-950 rounded-xl p-5 mb-6 space-y-3">
                    <div class="flex items-center justify-between text-xs font-bold text-indigo-300 uppercase tracking-wider pb-2 border-b border-slate-800">
                        <span>Price Breakdown</span>
                        <span class="text-[11px] font-normal lowercase text-slate-400">calculated in real-time</span>
                    </div>

                    <div class="flex justify-between text-xs text-slate-300">
                        <span>Duration:</span>
                        <span class="font-bold text-white"><span id="calcDays">0</span> days</span>
                    </div>

                    <div class="flex justify-between text-xs text-slate-300">
                        <span>Rental Charge (<span id="calcDaysFormula">0</span> × ₹<?= number_format($product->getRentPerDay(), 2) ?>):</span>
                        <span class="font-bold text-white">₹<span id="calcRentalAmount">0.00</span></span>
                    </div>

                    <div class="flex justify-between text-xs text-slate-300">
                        <span>Security Deposit (Refundable):</span>
                        <span class="font-bold text-emerald-400">₹<span id="calcDeposit">0.00</span></span>
                    </div>

                    <div class="pt-3 border-t border-slate-800 flex justify-between items-baseline">
                        <div>
                            <span class="text-sm font-bold text-white">Total Estimated:</span>
                            <span class="text-[10px] text-slate-400 block">Due only upon owner approval</span>
                        </div>
                        <div class="text-xl font-extrabold text-blue-400">
                            ₹<span id="calcTotalPayable">0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="flex items-center space-x-3">
                    <button type="submit" 
                            id="submitBtn"
                            class="flex-1 py-3.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-blue-500/25 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        Submit Booking Request &rarr;
                    </button>
                    <a href="<?= base_url('renter/product_details.php?id=' . $productId) ?>" 
                       class="px-5 py-3.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl transition">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const calcDays = document.getElementById('calcDays');
    const calcDaysFormula = document.getElementById('calcDaysFormula');
    const calcRentalAmount = document.getElementById('calcRentalAmount');
    const calcDeposit = document.getElementById('calcDeposit');
    const calcTotalPayable = document.getElementById('calcTotalPayable');
    const submitBtn = document.getElementById('submitBtn');

    const rentPerDay = <?= (float) $product->getRentPerDay() ?>;
    const securityDeposit = <?= (float) $product->getSecurityDeposit() ?>;

    function recalculate() {
        const startVal = startDateInput.value;
        const endVal = endDateInput.value;

        if (!startVal || !endVal) {
            updateUI(0, false);
            return;
        }

        const start = new Date(startVal);
        const end = new Date(endVal);

        const diffTime = end - start;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays <= 0) {
            calcDays.textContent = '0 (Invalid Range)';
            calcDaysFormula.textContent = '0 days';
            calcRentalAmount.textContent = '0.00';
            calcDeposit.textContent = Number(securityDeposit).toFixed(2);
            calcTotalPayable.textContent = '0.00';
            submitBtn.disabled = true;
            return;
        }

        submitBtn.disabled = false;
        const totalRent = diffDays * rentPerDay;
        const totalPayable = totalRent + securityDeposit;

        calcDays.textContent = diffDays;
        calcDaysFormula.textContent = diffDays + (diffDays === 1 ? ' day' : ' days');
        calcRentalAmount.textContent = totalRent.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        calcDeposit.textContent = Number(securityDeposit).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        calcTotalPayable.textContent = totalPayable.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    startDateInput.addEventListener('change', () => {
        // Automatically ensure end_date is at least 1 day after start_date
        if (startDateInput.value) {
            const nextDay = new Date(startDateInput.value);
            nextDay.setDate(nextDay.getDate() + 1);
            const minEndStr = nextDay.toISOString().split('T')[0];
            endDateInput.min = minEndStr;
            if (endDateInput.value && endDateInput.value <= startDateInput.value) {
                endDateInput.value = minEndStr;
            }
        }
        recalculate();
    });

    endDateInput.addEventListener('change', recalculate);

    // Initial run
    recalculate();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
