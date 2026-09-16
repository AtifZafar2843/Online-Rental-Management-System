<?php
/**
 * Online Rental Management System (ORMS)
 * Renter — Submit Product Review & Rating
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 3.10, 4, 5 (Rule 10)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/RentalRequest.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Review.php';
require_once __DIR__ . '/../classes/Renter.php';
require_once __DIR__ . '/../classes/exceptions/DuplicateReviewException.php';

require_role('Renter');

$userId = (int) $_SESSION['user_id'];
$requestId = (int) ($_GET['request_id'] ?? $_POST['request_id'] ?? 0);

if ($requestId <= 0) {
    set_flash('error', 'Invalid rental request specified.');
    redirect('renter/my_rentals.php');
}

$request = RentalRequest::findById($requestId);
if (!$request) {
    set_flash('error', 'Rental request not found.');
    redirect('renter/my_rentals.php');
}

// Ensure caller is the genuine renter
if ($request->getRenterID() !== $userId) {
    set_flash('error', 'You are not authorized to review this rental request.');
    redirect('renter/my_rentals.php');
}

// Rule 10: Must be Completed
if ($request->getStatus() !== 'Completed') {
    set_flash('error', "Reviews can only be submitted for 'Completed' rentals (Current status: {$request->getStatus()}).");
    redirect('renter/my_rentals.php');
}

$product = Product::findById($request->getProductID());
$existingReview = Review::findByRequest($requestId);

$errors = [];
$rating = (int) ($_POST['rating'] ?? 5);
$comment = trim((string) ($_POST['comment'] ?? ''));

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = "Security validation failed. Please refresh and try again.";
    }

    if ($existingReview !== null) {
        $errors[] = "A review has already been submitted for this rental.";
    }

    if ($rating < 1 || $rating > 5) {
        $errors[] = "Please choose a rating between 1 and 5 stars.";
    }

    if (empty($errors)) {
        try {
            $renter = new Renter($userId);
            $renter->submitReview($requestId, $rating, $comment);

            set_flash('success', "Thank you! Your {$rating}-star review for '{$request->getProductTitle()}' has been published.");
            redirect('renter/my_rentals.php');
        } catch (DuplicateReviewException $e) {
            $errors[] = $e->getMessage();
        } catch (Exception $e) {
            $errors[] = "Failed to submit review: " . $e->getMessage();
        }
    }
}

$page_title = "Write Review — " . ($product ? $product->getTitle() : 'Rental #' . $requestId);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-500 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('renter/my_rentals.php') ?>" class="hover:text-coral transition">My Rentals</a></li>
            <li><span>/</span></li>
            <li class="text-midnight font-semibold">Review Rental #<?= $requestId ?></li>
        </ol>
    </nav>

    <div class="bg-white border border-[#E9E7FF] rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
        <div class="flex items-center space-x-3 pb-4 border-b border-[#E9E7FF]">
            <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                <i class="ri-star-line"></i>
            </div>
            <div>
                <h1 class="text-xl font-display font-bold text-midnight tracking-tight">Rate & Review Your Experience</h1>
                <p class="text-xs text-slate-500 mt-0.5">Your honest rating helps fellow renters and builds community trust.</p>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs">
                <ul class="list-disc list-inside space-y-1">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Product Summary Header -->
        <div class="flex items-center space-x-4 p-4 rounded-2xl bg-[#FAF8F5] border border-[#E9E7FF]">
            <?php 
                $images = $product ? $product->getImages() : [];
                $thumb = !empty($images) ? $images[0]['image_path'] : 'assets/img/no-image.svg';
            ?>
            <img src="<?= base_url($thumb) ?>" alt="Product" class="w-16 h-16 rounded-2xl object-cover border border-[#E9E7FF] bg-slate-100">
            <div>
                <h3 class="font-display font-bold text-midnight text-sm"><?= htmlspecialchars($request->getProductTitle()) ?></h3>
                <div class="text-xs text-slate-500 mt-1">
                    <span>Rental Period: </span>
                    <span class="text-midnight font-medium"><?= date('M d, Y', strtotime($request->getStartDate())) ?> &rarr; <?= date('M d, Y', strtotime($request->getEndDate())) ?></span>
                </div>
            </div>
        </div>

        <?php if ($existingReview !== null): ?>
            <!-- Already Reviewed View -->
            <div class="p-5 rounded-2xl bg-emerald-50 border border-emerald-200 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-emerald-800 flex items-center space-x-1.5">
                        <i class="ri-checkbox-circle-fill text-emerald-600"></i>
                        <span>You have already submitted a review for this rental</span>
                    </span>
                    <span class="text-[11px] text-slate-500"><?= date('M d, Y', strtotime($existingReview->getReviewDate())) ?></span>
                </div>
                
                <div class="text-amber-500 text-lg tracking-wider">
                    <?= str_repeat('★', $existingReview->getRating()) . str_repeat('☆', 5 - $existingReview->getRating()) ?>
                    <span class="text-xs text-slate-600 ml-2 font-mono">(<?= $existingReview->getRating() ?> / 5)</span>
                </div>

                <?php if ($existingReview->getComment()): ?>
                    <p class="text-xs text-slate-700 bg-white p-3.5 rounded-2xl border border-emerald-100 italic">
                        "<?= htmlspecialchars($existingReview->getComment()) ?>"
                    </p>
                <?php endif; ?>

                <div class="pt-2 text-right">
                    <a href="<?= base_url('renter/my_rentals.php') ?>" class="text-xs text-coral hover:text-[#e04e53] font-semibold underline">
                        &larr; Back to My Rentals
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Review Form -->
            <form method="POST" action="" class="space-y-6">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= $requestId ?>">

                <!-- Star Rating Picker -->
                <div>
                    <label class="block text-xs font-semibold text-midnight mb-2">
                        Overall Rating <span class="text-coral">*</span>
                    </label>
                    
                    <div class="flex items-center space-x-2 text-2xl" id="starContainer">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <label class="cursor-pointer">
                                <input type="radio" name="rating" value="<?= $i ?>" <?= $rating === $i ? 'checked' : '' ?> onchange="updateStars(<?= $i ?>)" class="sr-only">
                                <span id="star-<?= $i ?>" class="transition hover:scale-110 inline-block <?= $i <= $rating ? 'text-amber-400' : 'text-slate-300' ?>">★</span>
                            </label>
                        <?php endfor; ?>
                        <span id="ratingLabel" class="text-xs text-amber-600 font-bold ml-3 font-mono"><?= $rating ?> / 5 Stars</span>
                    </div>
                </div>

                <!-- Comment Textarea -->
                <div>
                    <label for="comment" class="block text-xs font-semibold text-midnight mb-2">
                        Detailed Feedback & Experience (Optional)
                    </label>
                    <textarea id="comment" 
                              name="comment" 
                              rows="4" 
                              placeholder="How was the product condition? Was the owner communicative? Would you recommend renting this item to others?"
                              class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-3 text-xs text-midnight placeholder-slate-400 focus:outline-none focus:border-coral transition"><?= htmlspecialchars($comment) ?></textarea>
                    <p class="text-[10px] text-slate-400 mt-1">Your review will be publicly visible on the product's listing page.</p>
                </div>

                <div class="pt-4 border-t border-[#E9E7FF] flex items-center justify-between">
                    <a href="<?= base_url('renter/my_rentals.php') ?>" 
                       class="px-5 py-2.5 rounded-full text-xs font-semibold text-slate-600 hover:text-midnight bg-slate-100 transition">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-6 py-2.5 bg-coral hover:bg-[#e04e53] text-white font-semibold text-xs uppercase tracking-wider rounded-full shadow-glow-coral transition flex items-center space-x-2">
                        <span>Submit Review</span>
                        <span>&rarr;</span>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
function updateStars(val) {
    for (let i = 1; i <= 5; i++) {
        const star = document.getElementById('star-' + i);
        if (i <= val) {
            star.className = "text-amber-400 transition hover:scale-110 inline-block";
        } else {
            star.className = "text-slate-300 transition hover:scale-110 inline-block";
        }
    }
    const labels = ['', '1 / 5 (Poor)', '2 / 5 (Fair)', '3 / 5 (Good)', '4 / 5 (Very Good)', '5 / 5 (Excellent)'];
    document.getElementById('ratingLabel').innerText = labels[val] || (val + ' / 5 Stars');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
