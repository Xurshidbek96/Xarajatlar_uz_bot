<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600" rel="stylesheet" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #3b82f6;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #06b6d4;
            --dark-color: #1e293b;
            --light-color: #f8fafc;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Figtree', sans-serif;
            background-color: var(--light-color);
            color: var(--dark-color);
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1d4ed8 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-weight: 600;
            font-size: 1.25rem;
        }

        .sidebar {
            background: white;
            border-right: 1px solid var(--border-color);
            min-height: calc(100vh - 76px);
            box-shadow: 2px 0 4px rgba(0,0,0,0.05);
        }

        .sidebar .nav-link {
            color: var(--secondary-color);
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            margin: 0.25rem 0.75rem;
            transition: all 0.2s;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }

        .sidebar .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
        }

        .main-content {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin: 1.5rem;
            padding: 2rem;
        }

        .stats-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.2s;
        }

        .stats-card:hover {
            transform: translateY(-2px);
        }

        .stats-card .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stats-card .stats-label {
            color: var(--secondary-color);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .table {
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .table thead th {
            background-color: var(--light-color);
            border: none;
            font-weight: 600;
            color: var(--dark-color);
            padding: 1rem;
        }

        .table tbody td {
            padding: 1rem;
            border-color: var(--border-color);
        }

        .badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
        }

        .btn {
            border-radius: 0.5rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
        }

        .search-box {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--secondary-color);
            font-size: 1rem;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(59, 130, 246, 0.3);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), #1d4ed8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .status-online {
            color: var(--success-color);
        }

        .status-offline {
            color: var(--secondary-color);
        }
    </style>

    @stack('styles')
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('dashboard.index') }}">
                <i class="fas fa-chart-line me-2"></i>
                Finance Bot Dashboard
            </a>

            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        Admin
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('dashboard.broadcast') }}"><i class="fas fa-bullhorn me-2"></i>Broadcast xabarlar</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Sozlamalar</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#adminLoginModal"><i class="fas fa-key me-2"></i>Admin kirish (token)</a></li>
                        <li><a class="dropdown-item" href="#" id="adminLogout"><i class="fas fa-sign-out-alt me-2"></i>Chiqish</a></li>
                    </ul>
                </div>
                <span class="badge bg-light text-dark ms-3" id="authStatusBadge"><i class="fas fa-lock me-1"></i>Auth: Off</span>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                    <nav class="nav flex-column py-3">
                        <a class="nav-link {{ request()->routeIs('dashboard.index') ? 'active' : '' }}"
                           href="{{ route('dashboard.index') }}">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                        <a class="nav-link {{ request()->routeIs('dashboard.users*') ? 'active' : '' }}"
                           href="{{ route('dashboard.users') }}">
                            <i class="fas fa-users"></i>
                            Foydalanuvchilar
                        </a>
                        <a class="nav-link {{ request()->routeIs('dashboard.broadcast') ? 'active' : '' }}"
                           href="{{ route('dashboard.broadcast') }}">
                            <i class="fas fa-bullhorn"></i>
                            Broadcast xabarlar
                        </a>
                        <a class="nav-link" href="#">
                            <i class="fas fa-exchange-alt"></i>
                            Tranzaksiyalar
                        </a>
                        <a class="nav-link" href="#">
                            <i class="fas fa-tags"></i>
                            Kategoriyalar
                        </a>
                        <a class="nav-link" href="#">
                            <i class="fas fa-chart-bar"></i>
                            Hisobotlar
                        </a>
                        <a class="nav-link" href="#">
                            <i class="fas fa-cog"></i>
                            Sozlamalar
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    @yield('content')
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Login Modal -->
    <div class="modal fade" id="adminLoginModal" tabindex="-1" aria-labelledby="adminLoginModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="adminLoginModalLabel">Admin token olish</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="adminChatId" class="form-label">Admin chat_id</label>
                        <input type="text" class="form-control" id="adminChatId" placeholder="Masalan: 123456789">
                        <div class="form-text">ENV dagi `ADMIN_CHAT_ID` bilan mos bo‘lishi kerak.</div>
                    </div>
                    <div id="adminLoginFeedback" class="text-danger d-none">Xatolik yuz berdi. Iltimos, qayta urinib ko‘ring.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Yopish</button>
                    <button type="button" class="btn btn-primary" id="adminLoginSubmit">Token olish</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        function updateAuthStatusBadge() {
            const badge = document.getElementById('authStatusBadge');
            const hasToken = !!localStorage.getItem('admin_token');
            if (hasToken) {
                badge.classList.remove('bg-light', 'text-dark');
                badge.classList.add('bg-success');
                badge.innerHTML = '<i class="fas fa-lock-open me-1"></i>Auth: On';
            } else {
                badge.classList.remove('bg-success');
                badge.classList.add('bg-light', 'text-dark');
                badge.innerHTML = '<i class="fas fa-lock me-1"></i>Auth: Off';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateAuthStatusBadge();

            document.getElementById('adminLoginSubmit').addEventListener('click', async function() {
                const chatId = document.getElementById('adminChatId').value.trim();
                const feedback = document.getElementById('adminLoginFeedback');
                feedback.classList.add('d-none');
                if (!chatId) {
                    feedback.textContent = 'chat_id kiritish shart';
                    feedback.classList.remove('d-none');
                    return;
                }
                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    const resp = await fetch('/dashboard/api/admin/login', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({ chat_id: chatId })
                    });
                    if (!resp.ok) {
                        throw new Error('Login failed');
                    }
                    const data = await resp.json();
                    localStorage.setItem('admin_token', data.token);
                    updateAuthStatusBadge();
                    const modalEl = document.getElementById('adminLoginModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    modal.hide();
                } catch (e) {
                    feedback.textContent = 'Login muvaffaqiyatsiz. chat_id tekshiring.';
                    feedback.classList.remove('d-none');
                }
            });

            document.getElementById('adminLogout').addEventListener('click', function() {
                localStorage.removeItem('admin_token');
                updateAuthStatusBadge();
            });
        });
    </script>

    @stack('scripts')
</body>
</html>
