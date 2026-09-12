    </main>

    <!-- Global Application Footer -->
    <footer class="bg-slate-900 border-t border-slate-800 text-slate-400 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="md:col-span-2">
                    <div class="flex items-center space-x-2">
                        <div class="w-7 h-7 rounded-lg bg-blue-600 flex items-center justify-center font-bold text-white text-sm">O</div>
                        <span class="text-lg font-bold text-white tracking-wide">ORMS</span>
                    </div>
                    <p class="mt-3 text-sm text-slate-400 max-w-md leading-relaxed">
                        Online Rental Management System — Peer-to-peer rental marketplace designed to optimize asset utilization across Electronics, Furniture, and Vehicles.
                    </p>
                    <p class="mt-2 text-xs text-slate-500">
                        Academic Project &bull; BCSP-064 &bull; IGNOU BCA Final Project &bull; Student: Atif Zafar
                    </p>
                </div>
                <div>
                    <h3 class="text-xs font-semibold text-slate-200 uppercase tracking-wider">Quick Navigation</h3>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li><a href="<?= base_url('index.php') ?>" class="hover:text-white transition">Home</a></li>
                        <li><a href="<?= base_url('renter/search.php') ?>" class="hover:text-white transition">Browse Products</a></li>
                        <li><a href="<?= base_url('auth/login.php') ?>" class="hover:text-white transition">Account Sign In</a></li>
                        <li><a href="<?= base_url('auth/register.php') ?>" class="hover:text-white transition">Register Account</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-xs font-semibold text-slate-200 uppercase tracking-wider">Security & Standards</h3>
                    <ul class="mt-3 space-y-2 text-xs text-slate-400">
                        <li class="flex items-center space-x-2">
                            <span class="text-emerald-400">✔</span>
                            <span>Bcrypt Password Encryption</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <span class="text-emerald-400">✔</span>
                            <span>Magic-Byte File MIME Validation</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <span class="text-emerald-400">✔</span>
                            <span>Anti-CSRF & Native PDO Prepares</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <span class="text-emerald-400">✔</span>
                            <span>3NF Compliant Relational DB</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="mt-8 pt-6 border-t border-slate-800 text-center text-xs text-slate-500">
                &copy; <?= date('Y') ?> Online Rental Management System (ORMS). Built for IGNOU BCSP-064.
            </div>
        </div>
    </footer>
</body>
</html>
