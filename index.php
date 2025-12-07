<?php
// If user is already logged in, you can redirect them:
// session_start();
// if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
//     header('Location: hall.php');
//     exit;
// }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MediDesk | Smart Online Medical Appointment Service</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        :root {
            --glass-bg: rgba(15, 23, 42, 0.88);
            --glass-border: rgba(148, 163, 184, 0.4);
            --accent-primary: #38bdf8;
            --accent-secondary: #6366f1;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #020617;
            color: #e5e7eb;
            overflow-x: hidden;
        }

        .hero-bg {
            background-image: url('pp.jpg'); /* you can use same bg as reception */
            background-size: cover;
            background-position: center;
        }

        .glass-card {
            background: radial-gradient(circle at top left,
                        rgba(56,189,248,0.16) 0,
                        rgba(15,23,42,0.9) 35%,
                        rgba(15,23,42,0.98) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            box-shadow: 0 24px 80px rgba(15,23,42,0.9);
        }

        .pill {
            border-radius: 9999px;
            background: linear-gradient(to right, rgba(56,189,248,0.2), rgba(129,140,248,0.2));
            border: 1px solid rgba(148,163,184,0.5);
        }

        .floating-badge {
            animation: floatBadge 6s ease-in-out infinite;
        }

        @keyframes floatBadge {
            0%, 100% { transform: translateY(0px); opacity: 1; }
            50% { transform: translateY(-8px); opacity: 0.92; }
        }

        .feature-border {
            border-image: linear-gradient(to right, rgba(56,189,248,0.5), rgba(129,140,248,0.5)) 1;
        }
    </style>
</head>

<body class="antialiased">
<!-- NAVBAR -->
<header class="fixed top-0 left-0 right-0 z-30 bg-slate-950/70 backdrop-blur-xl border-b border-slate-800">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-sky-500 to-indigo-500 flex items-center justify-center text-white font-bold shadow-lg">
                MD
            </div>
            <div>
                <p class="font-semibold text-slate-50 tracking-tight">MediDesk</p>
                <p class="text-[11px] text-slate-400 leading-tight">
                    Smart Medical Appointment Service
                </p>
            </div>
        </div>

        <nav class="hidden md:flex items-center gap-6 text-sm text-slate-300">
            <a href="#how" class="hover:text-sky-400">How it works</a>
            <a href="#features" class="hover:text-sky-400">Features</a>
            <a href="#doctors" class="hover:text-sky-400">Top Doctors</a>
            <a href="#faq" class="hover:text-sky-400">FAQ</a>
        </nav>

        <div class="flex items-center gap-2">
            <a href="login.php"
               class="px-3 py-1.5 text-xs sm:text-sm rounded-full border border-slate-600 text-slate-200 hover:border-sky-400 hover:text-sky-300 transition">
                Log in
            </a>
            <a href="signup.php"
               class="px-3 sm:px-4 py-1.5 text-xs sm:text-sm rounded-full bg-gradient-to-r from-sky-500 to-indigo-500 text-white font-medium shadow-lg hover:from-sky-400 hover:to-indigo-400 transition">
                Create account
            </a>
        </div>
    </div>
</header>

<!-- HERO -->
<section class="relative hero-bg pt-28 pb-20">
    <div class="absolute inset-0 bg-gradient-to-b from-slate-950/85 via-slate-950/80 to-slate-950/95"></div>

    <div class="relative max-w-6xl mx-auto px-4 flex flex-col lg:flex-row gap-10 lg:gap-14 items-center">
        <!-- Left side -->
        <div class="flex-1">
            <div class="inline-flex items-center gap-2 pill px-3 py-1 mb-4 text-xs text-sky-100">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                Online appointments • 24/7 booking • No waiting line
            </div>

            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-slate-50 mb-3">
                Book your doctor
                <span class="block text-transparent bg-clip-text bg-gradient-to-r from-sky-400 to-indigo-400">
                    in just a few clicks.
                </span>
            </h1>

            <p class="text-sm sm:text-base text-slate-300 max-w-xl mb-6">
                MediDesk connects patients, receptionists, and doctors in one smart system.
                Search doctors, book appointments online, and let the reception manage everything —
                so you just show up on time.
            </p>

            <div class="flex flex-wrap items-center gap-3 mb-6">
                <a href="signup.php"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-gradient-to-r from-sky-500 to-indigo-500 text-sm font-medium text-white shadow-xl hover:from-sky-400 hover:to-indigo-400">
                    Get Started — It’s Free
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14"></path>
                        <path d="m12 5 7 7-7 7"></path>
                    </svg>
                </a>

                <a href="#how"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-slate-600 text-sm text-slate-200 hover:border-sky-400 hover:text-sky-300">
                    See how it works
                </a>
            </div>

            <div class="flex flex-wrap gap-5 text-xs text-slate-400">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-full bg-sky-500/20 border border-sky-500/60 flex items-center justify-center text-sky-300 text-[11px]">
                        24/7
                    </span>
                    <span>Online booking anytime</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-full bg-emerald-500/15 border border-emerald-400/50 flex items-center justify-center text-emerald-300 text-[11px]">
                        ✓
                    </span>
                    <span>No long queue at hospital</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-full bg-indigo-500/15 border border-indigo-400/50 flex items-center justify-center text-indigo-300 text-[11px]">
                        🔒
                    </span>
                    <span>Secure & role-based access</span>
                </div>
            </div>
        </div>

        <!-- Right side: appointment preview card -->
        <div class="flex-1 w-full max-w-md lg:max-w-lg">
            <div class="glass-card rounded-3xl p-6 sm:p-7 relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-sky-500/20 blur-3xl"></div>
                <div class="absolute -bottom-16 -left-8 w-52 h-52 rounded-full bg-indigo-500/20 blur-3xl"></div>

                <div class="relative flex justify-between items-center mb-4">
                    <div>
                        <p class="text-xs text-slate-400">Live preview</p>
                        <h2 class="text-lg font-semibold text-slate-50">Today’s Appointments</h2>
                    </div>
                    <span class="floating-badge px-3 py-1 rounded-full bg-emerald-500/15 border border-emerald-400/40 text-[11px] text-emerald-300">
                        Reception smart view
                    </span>
                </div>

                <div class="relative border border-slate-700/80 rounded-2xl bg-slate-900/60">
                    <div class="flex justify-between items-center px-4 py-2 border-b border-slate-700/80 text-[11px] text-slate-400">
                        <span>Dr. Dashboard</span>
                        <span>Patient Portal</span>
                        <span>Reception</span>
                    </div>

                    <div class="p-4 space-y-3 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-400">09:30 AM</span>
                            <span class="text-slate-50 font-medium">Fever & checkup</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[11px] text-slate-400">Patient</p>
                                <p class="text-sm text-slate-100">Nusrat Jahan</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[11px] text-slate-400">Doctor</p>
                                <p class="text-sm text-slate-100">Dr. Rahman</p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-200">
                                Waiting
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-sky-500/20 text-sky-200">
                                Online booked
                            </span>
                        </div>

                        <div class="mt-4 border-t border-slate-700/80 pt-3 flex justify-between text-[11px] text-slate-400">
                            <span>Patients can cancel from their own panel ✔</span>
                            <span>Real-time updates</span>
                        </div>
                    </div>
                </div>

                <div class="relative mt-5 text-[11px] text-slate-400 flex flex-wrap gap-3">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        <span>Doctor sees confirmed list</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-sky-400"></span>
                        <span>Reception controls booking flow</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
                        <span>Patient manages own visits</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section id="how" class="bg-slate-950 py-14 border-t border-slate-800/60">
    <div class="max-w-6xl mx-auto px-4">
        <h2 class="text-2xl font-bold text-slate-50 mb-2">How MediDesk works</h2>
        <p class="text-sm text-slate-400 mb-6 max-w-xl">
            Simple 3-step flow connecting patient → reception → doctor. Exactly how you designed your backend.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="border border-slate-800 rounded-2xl p-5 bg-slate-900/60 feature-border">
                <div class="w-8 h-8 rounded-full bg-sky-500/20 flex items-center justify-center text-sky-300 mb-3 text-sm">1</div>
                <h3 class="text-sm font-semibold text-slate-50 mb-1">Patient searches & books</h3>
                <p class="text-xs text-slate-400">
                    Patients browse doctor list, filter by specialization, chamber, or fee,
                    and request an appointment online.
                </p>
            </div>
            <div class="border border-slate-800 rounded-2xl p-5 bg-slate-900/60 feature-border">
                <div class="w-8 h-8 rounded-full bg-indigo-500/20 flex items-center justify-center text-indigo-300 mb-3 text-sm">2</div>
                <h3 class="text-sm font-semibold text-slate-50 mb-1">Reception manages schedule</h3>
                <p class="text-xs text-slate-400">
                    Receptionist sees all incoming requests in your <code>jo.php</code> panel, confirms or holds them,
                    and arranges daily queue.
                </p>
            </div>
            <div class="border border-slate-800 rounded-2xl p-5 bg-slate-900/60 feature-border">
                <div class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-300 mb-3 text-sm">3</div>
                <h3 class="text-sm font-semibold text-slate-50 mb-1">Doctor checks & completes</h3>
                <p class="text-xs text-slate-400">
                    Doctors log into their dashboard, see upcoming visits, update status,
                    and write mini-prescriptions right in the system.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section id="features" class="bg-gradient-to-b from-slate-950 via-slate-950 to-slate-950 py-14">
    <div class="max-w-6xl mx-auto px-4">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-2xl font-bold text-slate-50">Designed for real clinics</h2>
                <p class="text-sm text-slate-400">
                    Not just a template. It matches your doctor / patient / reception roles from the backend.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="border border-slate-800 rounded-2xl p-5 bg-slate-900/70">
                <p class="text-xs uppercase text-sky-400 font-semibold mb-1">Patient Portal</p>
                <h3 class="text-sm font-semibold text-slate-50 mb-2">Self-service appointments</h3>
                <p class="text-xs text-slate-400 mb-3">
                    Patients can see upcoming visits, cancel if needed, and add notes (symptoms, questions)
                    before the consultation.
                </p>
            </div>

            <div class="border border-slate-800 rounded-2xl p-5 bg-slate-900/70">
                <p class="text-xs uppercase text-indigo-400 font-semibold mb-1">Doctor Dashboard</p>
                <h3 class="text-sm font-semibold text-slate-50 mb-2">Smart clinical view</h3>
                <p class="text-xs text-slate-400 mb-3">
                    Doctors see only their own patients, can confirm, mark completed, cancel,
                    and write quick prescriptions inside each appointment.
                </p>
            </div>

            <div class="border border-slate-800 rounded-2xl p-5 bg-slate-900/70">
                <p class="text-xs uppercase text-emerald-400 font-semibold mb-1">Reception Panel</p>
                <h3 class="text-sm font-semibold text-slate-50 mb-2">Full control</h3>
                <p class="text-xs text-slate-400 mb-3">
                    Receptionist dashboard shows today’s schedule, patient directory,
                    billing snapshots, and lets them create new appointments.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- DOCTORS PREVIEW (dummy – front-end only) -->
<section id="doctors" class="bg-slate-950 py-14 border-t border-slate-800/60">
    <div class="max-w-6xl mx-auto px-4">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mb-6">
            <div>
                <h2 class="text-2xl font-bold text-slate-50">Meet some of our specialists</h2>
                <p class="text-sm text-slate-400">
                    This can later be wired with your <code>doctors</code> table.
                </p>
            </div>
            <a href="doctor_search.php"
               class="text-xs px-4 py-2 rounded-full border border-slate-600 text-slate-200 hover:border-sky-400 hover:text-sky-300">
                Explore all doctors
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-4">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-full bg-slate-800"></div>
                    <div>
                        <p class="text-sm font-semibold text-slate-50">Dr. Rahman</p>
                        <p class="text-[11px] text-slate-400">Cardiologist • 10+ yrs exp.</p>
                    </div>
                </div>
                <p class="text-xs text-slate-400">
                    “MediDesk makes my OPD smooth. I just open dashboard, see confirmed list and go one by one.”
                </p>
            </div>

            <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-4">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-full bg-slate-800"></div>
                    <div>
                        <p class="text-sm font-semibold text-slate-50">Dr. Ayesha</p>
                        <p class="text-[11px] text-slate-400">Gynecologist • 8+ yrs exp.</p>
                    </div>
                </div>
                <p class="text-xs text-slate-400">
                    “Patients can book and cancel themselves. Reception doesn’t need paper registers anymore.”
                </p>
            </div>

            <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-4">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-full bg-slate-800"></div>
                    <div>
                        <p class="text-sm font-semibold text-slate-50">Dr. Hasan</p>
                        <p class="text-[11px] text-slate-400">General Physician • 5+ yrs exp.</p>
                    </div>
                </div>
                <p class="text-xs text-slate-400">
                    “I like that I can mark ‘Completed’ and keep a small note / prescription inside each appointment.”
                </p>
            </div>
        </div>
    </div>
</section>

<!-- FAQ / CTA -->
<section id="faq" class="bg-slate-950 py-12 border-t border-slate-800/60">
    <div class="max-w-6xl mx-auto px-4 grid grid-cols-1 md:grid-cols-2 gap-10">
        <div>
            <h2 class="text-2xl font-bold text-slate-50 mb-3">Frequently asked questions</h2>
            <div class="space-y-4 text-sm text-slate-300">
                <div>
                    <p class="font-semibold text-slate-100 text-sm">Is this system multi-role?</p>
                    <p class="text-xs text-slate-400">
                        Yes. There are separate dashboards for patients, doctors, and reception.
                        Access is controlled by login role.
                    </p>
                </div>
                <div>
                    <p class="font-semibold text-slate-100 text-sm">Can patients cancel appointments?</p>
                    <p class="text-xs text-slate-400">
                        Yes. From the patient dashboard they can cancel upcoming visits and add notes.
                    </p>
                </div>
                <div>
                    <p class="font-semibold text-slate-100 text-sm">Can reception create appointments manually?</p>
                    <p class="text-xs text-slate-400">
                        Yes. Reception can select doctor + patient from dropdown and create appointments
                        from the MediDesk reception panel.
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-slate-900/80 border border-slate-700 rounded-2xl p-6">
            <h3 class="text-lg font-semibold text-slate-50 mb-2">
                Ready to plug your backend into this UI?
            </h3>
            <p class="text-xs text-slate-400 mb-4">
                You already have login, signup, and dashboards. This landing page is just the nice front door.
                Link the buttons to your existing PHP routes and you’re done.
            </p>

            <div class="flex flex-wrap gap-2 mb-4">
                <a href="signup.php"
                   class="px-4 py-2 rounded-full bg-gradient-to-r from-sky-500 to-indigo-500 text-xs font-medium text-white">
                    Create patient / doctor account
                </a>
                <a href="login.php"
                   class="px-4 py-2 rounded-full border border-slate-600 text-xs text-slate-200 hover:border-sky-400 hover:text-sky-300">
                    Log in to dashboard
                </a>
            </div>

            <p class="text-[11px] text-slate-500">
                Later, you can add pricing, contact form, or hospital branding here.
            </p>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="bg-slate-950 border-t border-slate-800/80 py-5">
    <div class="max-w-6xl mx-auto px-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <p class="text-[11px] text-slate-500">
            © <?php echo date('Y'); ?> MediDesk. Built for modern clinics & chambers.
        </p>
        <p class="text-[11px] text-slate-500">
            Powered by your custom PHP + Tailwind backend.
        </p>
    </div>
</footer>

</body>
</html>
