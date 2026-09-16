    </main>

    <!-- Global Application Footer — Brand System Integration -->
    <footer class="bg-midnight text-stone-300 border-t border-midnight-800 mt-20 pt-16 pb-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Brand Pillars Ribbon -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 pb-12 mb-12 border-b border-stone-800">
                <div class="flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-stone-800/80 flex items-center justify-center text-coral text-xl flex-shrink-0">
                        <i class="ri-key-2-line"></i>
                    </div>
                    <div>
                        <h4 class="font-display font-semibold text-sm text-white">Open Access</h4>
                        <p class="text-xs text-stone-400 mt-0.5">Everyday & special items</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-stone-800/80 flex items-center justify-center text-coral text-xl flex-shrink-0">
                        <i class="ri-group-line"></i>
                    </div>
                    <div>
                        <h4 class="font-display font-semibold text-sm text-white">Stronger Community</h4>
                        <p class="text-xs text-stone-400 mt-0.5">Trusted peer network</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-stone-800/80 flex items-center justify-center text-coral text-xl flex-shrink-0">
                        <i class="ri-shield-check-line"></i>
                    </div>
                    <div>
                        <h4 class="font-display font-semibold text-sm text-white">Trusted Transactions</h4>
                        <p class="text-xs text-stone-400 mt-0.5">Escrow-backed security</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-stone-800/80 flex items-center justify-center text-coral text-xl flex-shrink-0">
                        <i class="ri-leaf-line"></i>
                    </div>
                    <div>
                        <h4 class="font-display font-semibold text-sm text-white">Sustainable Choices</h4>
                        <p class="text-xs text-stone-400 mt-0.5">Own less. Access more.</p>
                    </div>
                </div>
            </div>

            <!-- Footer Columns -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-10">
                <div class="md:col-span-2 space-y-4">
                    <a href="<?= base_url('index.php') ?>" class="inline-block">
                        <img src="<?= base_url('assets/img/ORMS Logo.png') ?>" alt="ORMS" class="h-11 sm:h-12 w-auto brightness-0 invert opacity-95">
                    </a>
                    <p class="text-xs text-stone-400 leading-relaxed max-w-sm">
                        ORMS is a modern peer-to-peer rental marketplace where people list what they have and rent what they need. Making high-value assets accessible, affordable, and sustainable.
                    </p>
                    <div class="flex items-center space-x-3 pt-2 text-stone-400">
                        <a href="#" class="w-8 h-8 rounded-full bg-stone-800/80 hover:bg-coral hover:text-white flex items-center justify-center transition"><i class="ri-instagram-line"></i></a>
                        <a href="#" class="w-8 h-8 rounded-full bg-stone-800/80 hover:bg-coral hover:text-white flex items-center justify-center transition"><i class="ri-twitter-x-line"></i></a>
                        <a href="#" class="w-8 h-8 rounded-full bg-stone-800/80 hover:bg-coral hover:text-white flex items-center justify-center transition"><i class="ri-linkedin-fill"></i></a>
                        <a href="#" class="w-8 h-8 rounded-full bg-stone-800/80 hover:bg-coral hover:text-white flex items-center justify-center transition"><i class="ri-github-line"></i></a>
                    </div>
                </div>

                <div>
                    <h3 class="font-display text-xs font-bold text-white uppercase tracking-wider">Explore</h3>
                    <ul class="mt-4 space-y-2.5 text-xs text-stone-400">
                        <li><a href="<?= base_url('index.php') ?>" class="hover:text-coral transition">Home Marketplace</a></li>
                        <li><a href="<?= base_url('renter/search.php') ?>" class="hover:text-coral transition">Browse Catalog</a></li>
                        <li><a href="<?= base_url('renter/search.php?category=1') ?>" class="hover:text-coral transition">Electronics & Cameras</a></li>
                        <li><a href="<?= base_url('renter/search.php?category=2') ?>" class="hover:text-coral transition">Home & Furniture</a></li>
                        <li><a href="<?= base_url('renter/search.php?category=3') ?>" class="hover:text-coral transition">Bikes & Vehicles</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-display text-xs font-bold text-white uppercase tracking-wider">Account & Hosting</h3>
                    <ul class="mt-4 space-y-2.5 text-xs text-stone-400">
                        <li><a href="<?= base_url('auth/login.php') ?>" class="hover:text-coral transition">Sign In</a></li>
                        <li><a href="<?= base_url('auth/register.php') ?>" class="hover:text-coral transition">Create Account</a></li>
                        <li><a href="<?= base_url('owner/add_product.php') ?>" class="hover:text-coral transition">List Your Item</a></li>
                        <li><a href="<?= base_url('renter/my_rentals.php') ?>" class="hover:text-coral transition">Rental Bookings</a></li>
                        <li><a href="<?= base_url('notifications/view_notifications.php') ?>" class="hover:text-coral transition">Notification Center</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-display text-xs font-bold text-white uppercase tracking-wider">Trust & Security</h3>
                    <ul class="mt-4 space-y-2.5 text-xs text-stone-400">
                        <li class="flex items-center space-x-2">
                            <i class="ri-shield-check-fill text-emerald-400 text-sm"></i>
                            <span>Verified Identity System</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i class="ri-lock-2-fill text-emerald-400 text-sm"></i>
                            <span>Encrypted Sessions & Data</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i class="ri-safe-2-fill text-emerald-400 text-sm"></i>
                            <span>Protected Escrow Deposits</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i class="ri-scales-3-fill text-emerald-400 text-sm"></i>
                            <span>Fair Dispute Adjudication</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Copyright & Tagline -->
            <div class="mt-12 pt-6 border-t border-stone-800/80 flex flex-col sm:flex-row items-center justify-between text-xs text-stone-500 gap-4">
                <div>
                    &copy; <?= date('Y') ?> ORMS Rental Marketplace Inc. All rights reserved.
                </div>
                <div class="flex items-center space-x-6 text-stone-400">
                    <span class="text-coral font-medium">Own less. Access more.</span>
                    <a href="#" class="hover:text-stone-300 transition">Privacy Policy</a>
                    <a href="#" class="hover:text-stone-300 transition">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
