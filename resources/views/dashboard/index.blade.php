@extends('dashboard.layout')

@section('title', 'Dashboard')

@section('content')
<div class="page-header">
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Finance Bot statistikalari va umumiy ma'lumotlar</p>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stats-card">
            <div class="stats-number" id="total-users">
                <div class="loading"></div>
            </div>
            <div class="stats-label">Jami foydalanuvchilar</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stats-card">
            <div class="stats-number" id="active-users">
                <div class="loading"></div>
            </div>
            <div class="stats-label">Faol foydalanuvchilar</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stats-card">
            <div class="stats-number" id="total-transactions">
                <div class="loading"></div>
            </div>
            <div class="stats-label">Jami tranzaksiyalar</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stats-card">
            <div class="stats-number" id="total-amount">
                <div class="loading"></div>
            </div>
            <div class="stats-label">Jami summa</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Kunlik faollik
                </h5>
            </div>
            <div class="card-body">
                <canvas id="dailyActivityChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Tranzaksiya turlari
                </h5>
            </div>
            <div class="card-body">
                <canvas id="transactionTypesChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Recent Users -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-users me-2"></i>
                    So'nggi foydalanuvchilar
                </h5>
                <a href="{{ route('dashboard.users') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-eye me-1"></i>
                    Barchasini ko'rish
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Foydalanuvchi</th>
                                <th>Telegram ID</th>
                                <th>Ro'yxatdan o'tgan</th>
                                <th>So'nggi faollik</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="recent-users-table">
                            <tr>
                                <td colspan="5" class="text-center">
                                    <div class="loading"></div>
                                    Ma'lumotlar yuklanmoqda...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load dashboard statistics
    loadDashboardStats();
    
    // Load recent users
    loadRecentUsers();
    
    // Initialize charts
    initializeCharts();
});

function getAuthHeaders() {
    const token = localStorage.getItem('admin_token');
    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    };
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }
    return headers;
}

function loadDashboardStats() {
    fetch('/dashboard/api/stats', { headers: getAuthHeaders() })
        .then(response => response.json())
        .then(data => {
            document.getElementById('total-users').innerHTML = data.total_users || '0';
            document.getElementById('active-users').innerHTML = data.active_users || '0';
            document.getElementById('total-transactions').innerHTML = data.total_transactions || '0';
            document.getElementById('total-amount').innerHTML = (data.total_amount || '0') + ' so\'m';
        })
        .catch(error => {
            console.error('Error loading stats:', error);
            document.getElementById('total-users').innerHTML = '0';
            document.getElementById('active-users').innerHTML = '0';
            document.getElementById('total-transactions').innerHTML = '0';
            document.getElementById('total-amount').innerHTML = '0 so\'m';
        });
}

function loadRecentUsers() {
    fetch('/dashboard/api/recent-users', { headers: getAuthHeaders() })
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('recent-users-table');
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Hozircha foydalanuvchilar yo\'q</td></tr>';
                return;
            }
            
            tbody.innerHTML = data.map(user => `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="user-avatar me-3">
                                ${user.name ? user.name.charAt(0).toUpperCase() : 'U'}
                            </div>
                            <div>
                                <div class="fw-medium">${user.name || 'Noma\'lum'}</div>
                                <small class="text-muted">@${user.username || 'username_yoq'}</small>
                            </div>
                        </div>
                    </td>
                    <td><code>${user.chat_id}</code></td>
                    <td>${formatDate(user.created_at)}</td>
                    <td>${formatDate(user.updated_at)}</td>
                    <td>
                        <span class="badge ${isUserActive(user.updated_at) ? 'bg-success' : 'bg-secondary'}">
                            ${isUserActive(user.updated_at) ? 'Faol' : 'Nofaol'}
                        </span>
                    </td>
                </tr>
            `).join('');
        })
        .catch(error => {
            console.error('Error loading recent users:', error);
            document.getElementById('recent-users-table').innerHTML = 
                '<tr><td colspan="5" class="text-center text-danger">Ma\'lumotlarni yuklashda xatolik yuz berdi</td></tr>';
        });
}

function initializeCharts() {
    // Daily Activity Chart
    const dailyCtx = document.getElementById('dailyActivityChart').getContext('2d');
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: ['Dush', 'Sesh', 'Chor', 'Pay', 'Jum', 'Shan', 'Yak'],
            datasets: [{
                label: 'Faol foydalanuvchilar',
                data: [12, 19, 3, 5, 2, 3, 10],
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Transaction Types Chart
    const typesCtx = document.getElementById('transactionTypesChart').getContext('2d');
    new Chart(typesCtx, {
        type: 'doughnut',
        data: {
            labels: ['Daromad', 'Xarajat', 'O\'tkazma'],
            datasets: [{
                data: [300, 150, 50],
                backgroundColor: [
                    'rgb(16, 185, 129)',
                    'rgb(239, 68, 68)',
                    'rgb(245, 158, 11)'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('uz-UZ', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function isUserActive(updatedAt) {
    const lastActivity = new Date(updatedAt);
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);
    return lastActivity >= weekAgo;
}
</script>
@endpush