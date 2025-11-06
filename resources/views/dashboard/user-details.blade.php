@extends('dashboard.layout')

@section('title', 'Foydalanuvchi ma\'lumotlari')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">Foydalanuvchi ma'lumotlari</h1>
            <p class="page-subtitle">Foydalanuvchi profili va tranzaksiya tarixi</p>
        </div>
        <a href="{{ route('dashboard.users') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>
            Orqaga
        </a>
    </div>
</div>

<!-- User Profile Card -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;" id="user-avatar">
                    U
                </div>
                <h4 class="card-title" id="user-name">Yuklanmoqda...</h4>
                <p class="text-muted" id="user-username">@username</p>
                <div class="row text-center mt-4">
                    <div class="col-4">
                        <div class="fw-bold" id="user-transactions">0</div>
                        <small class="text-muted">Tranzaksiyalar</small>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold" id="user-balance">0</div>
                        <small class="text-muted">Balans</small>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold" id="user-days">0</div>
                        <small class="text-muted">Kunlar</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Umumiy ma'lumotlar
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="text-muted">Telegram ID:</td>
                                <td><code id="user-telegram-id">-</code></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Ism:</td>
                                <td id="user-first-name">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Familiya:</td>
                                <td id="user-last-name">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Username:</td>
                                <td id="user-username-detail">-</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="text-muted">Ro'yxatdan o'tgan:</td>
                                <td id="user-created-at">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">So'nggi faollik:</td>
                                <td id="user-updated-at">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Status:</td>
                                <td id="user-status">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Til:</td>
                                <td id="user-language">-</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-number text-success" id="total-income">
                <div class="loading"></div>
            </div>
            <div class="stats-label">Jami daromad</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-number text-danger" id="total-expense">
                <div class="loading"></div>
            </div>
            <div class="stats-label">Jami xarajat</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-number text-info" id="total-transfers">
                <div class="loading"></div>
            </div>
            <div class="stats-label">O'tkazmalar</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-number text-primary" id="avg-transaction">
                <div class="loading"></div>
            </div>
            <div class="stats-label">O'rtacha tranzaksiya</div>
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
                    Oylik tranzaksiyalar
                </h5>
            </div>
            <div class="card-body">
                <canvas id="monthlyTransactionsChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Kategoriyalar
                </h5>
            </div>
            <div class="card-body">
                <canvas id="categoriesChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Transactions Table -->
<div class="card" id="transactions">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-exchange-alt me-2"></i>
            Tranzaksiyalar tarixi
        </h5>
        <div class="d-flex gap-2">
            <select class="form-select form-select-sm" id="transactionTypeFilter" style="width: auto;">
                <option value="">Barcha turlar</option>
                <option value="income">Daromad</option>
                <option value="expense">Xarajat</option>
                <option value="transfer">O'tkazma</option>
            </select>
            <button class="btn btn-outline-primary btn-sm" onclick="exportTransactions()">
                <i class="fas fa-download me-1"></i>
                Eksport
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Sana</th>
                        <th>Tur</th>
                        <th>Kategoriya</th>
                        <th>Tavsif</th>
                        <th>Summa</th>
                        <th>Balans</th>
                    </tr>
                </thead>
                <tbody id="transactions-table">
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <div class="loading"></div>
                            <div class="mt-2">Tranzaksiyalar yuklanmoqda...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <nav aria-label="Tranzaksiyalar navigatsiyasi" class="mt-4">
            <ul class="pagination justify-content-center" id="transactions-pagination">
                <!-- Pagination will be loaded here -->
            </ul>
        </nav>
    </div>
</div>
@endsection

@push('scripts')
<script>
let userId = {{ $userId ?? 'null' }};
let currentTransactionPage = 1;
let currentTransactionType = '';

document.addEventListener('DOMContentLoaded', function() {
    if (userId) {
        loadUserDetails();
        loadUserStats();
        loadUserTransactions();
        initializeCharts();
    }
    
    // Transaction type filter
    document.getElementById('transactionTypeFilter').addEventListener('change', function() {
        currentTransactionType = this.value;
        currentTransactionPage = 1;
        loadUserTransactions();
    });
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

function loadUserDetails() {
    fetch(`/dashboard/api/users/${userId}`, { headers: getAuthHeaders() })
        .then(response => response.json())
        .then(user => {
            // Update user avatar
            const avatar = document.getElementById('user-avatar');
            avatar.textContent = user.name ? user.name.charAt(0).toUpperCase() : 'U';
            
            // Update user info
            document.getElementById('user-name').textContent = 
                user.name || 'Noma\'lum';
            document.getElementById('user-username').textContent = `@${user.username || 'username_yoq'}`;
            
            // Update details
            document.getElementById('user-telegram-id').textContent = user.chat_id;
            document.getElementById('user-first-name').textContent = user.name || '-';
            document.getElementById('user-last-name').textContent = '-';
            document.getElementById('user-username-detail').textContent = user.username || '-';
            document.getElementById('user-created-at').textContent = formatDate(user.created_at);
            document.getElementById('user-updated-at').textContent = formatDate(user.updated_at);
            document.getElementById('user-language').textContent = user.language_code || 'uz';
            
            // Update status
            const statusElement = document.getElementById('user-status');
            statusElement.innerHTML = `
                <span class="badge ${isUserActive(user.updated_at) ? 'bg-success' : 'bg-secondary'}">
                    <i class="fas ${isUserActive(user.updated_at) ? 'fa-check-circle' : 'fa-times-circle'} me-1"></i>
                    ${isUserActive(user.updated_at) ? 'Faol' : 'Nofaol'}
                </span>
            `;
            
            // Update counters
            document.getElementById('user-transactions').textContent = user.transactions_count || 0;
            document.getElementById('user-balance').textContent = formatCurrency(user.balance || 0);
            
            const daysSinceRegistration = Math.floor((new Date() - new Date(user.created_at)) / (1000 * 60 * 60 * 24));
            document.getElementById('user-days').textContent = daysSinceRegistration;
        })
        .catch(error => {
            console.error('Error loading user details:', error);
        });
}

function loadUserStats() {
    fetch(`/dashboard/api/users/${userId}/stats`, { headers: getAuthHeaders() })
        .then(response => response.json())
        .then(stats => {
            document.getElementById('total-income').textContent = formatCurrency(stats.total_income || 0);
            document.getElementById('total-expense').textContent = formatCurrency(stats.total_expense || 0);
            document.getElementById('total-transfers').textContent = stats.total_transfers || 0;
            document.getElementById('avg-transaction').textContent = formatCurrency(stats.avg_transaction || 0);
        })
        .catch(error => {
            console.error('Error loading user stats:', error);
            document.getElementById('total-income').textContent = '0 so\'m';
            document.getElementById('total-expense').textContent = '0 so\'m';
            document.getElementById('total-transfers').textContent = '0';
            document.getElementById('avg-transaction').textContent = '0 so\'m';
        });
}

function loadUserTransactions() {
    const params = new URLSearchParams({
        page: currentTransactionPage,
        type: currentTransactionType
    });
    
    fetch(`/dashboard/api/users/${userId}/transactions?${params}`, { headers: getAuthHeaders() })
        .then(response => response.json())
        .then(data => {
            renderTransactionsTable(data.data);
            renderTransactionsPagination(data);
        })
        .catch(error => {
            console.error('Error loading transactions:', error);
            document.getElementById('transactions-table').innerHTML = 
                '<tr><td colspan="6" class="text-center text-danger">Tranzaksiyalarni yuklashda xatolik yuz berdi</td></tr>';
        });
}

function renderTransactionsTable(transactions) {
    const tbody = document.getElementById('transactions-table');
    
    if (transactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Tranzaksiyalar topilmadi</td></tr>';
        return;
    }
    
    tbody.innerHTML = transactions.map(transaction => `
        <tr>
            <td>
                <div>${formatDate(transaction.created_at)}</div>
                <small class="text-muted">${getTimeAgo(transaction.created_at)}</small>
            </td>
            <td>
                <span class="badge ${getTransactionTypeBadge(transaction.type)}">
                    <i class="${getTransactionTypeIcon(transaction.type)} me-1"></i>
                    ${getTransactionTypeText(transaction.type)}
                </span>
            </td>
            <td>${transaction.category || '-'}</td>
            <td>
                <div class="text-truncate" style="max-width: 200px;" title="${transaction.description || '-'}">
                    ${transaction.description || '-'}
                </div>
            </td>
            <td>
                <span class="fw-bold ${transaction.type === 'expense' ? 'text-danger' : 'text-success'}">
                    ${transaction.type === 'expense' ? '-' : '+'}${formatCurrency(transaction.amount)}
                </span>
            </td>
            <td>
                <span class="text-muted">${formatCurrency(transaction.balance_after || 0)}</span>
            </td>
        </tr>
    `).join('');
}

function renderTransactionsPagination(data) {
    const pagination = document.getElementById('transactions-pagination');
    const totalPages = data.last_page;
    const currentPage = data.current_page;
    
    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }
    
    let paginationHTML = '';
    
    // Previous button
    if (currentPage > 1) {
        paginationHTML += `<li class="page-item">
            <a class="page-link" href="#" onclick="changeTransactionPage(${currentPage - 1})">Oldingi</a>
        </li>`;
    }
    
    // Page numbers
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        paginationHTML += `<li class="page-item ${i === currentPage ? 'active' : ''}">
            <a class="page-link" href="#" onclick="changeTransactionPage(${i})">${i}</a>
        </li>`;
    }
    
    // Next button
    if (currentPage < totalPages) {
        paginationHTML += `<li class="page-item">
            <a class="page-link" href="#" onclick="changeTransactionPage(${currentPage + 1})">Keyingi</a>
        </li>`;
    }
    
    pagination.innerHTML = paginationHTML;
}

function changeTransactionPage(page) {
    currentTransactionPage = page;
    loadUserTransactions();
}

function initializeCharts() {
    // Monthly Transactions Chart
    const monthlyCtx = document.getElementById('monthlyTransactionsChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: ['Yan', 'Fev', 'Mar', 'Apr', 'May', 'Iyun', 'Iyul', 'Avg', 'Sen', 'Okt', 'Noy', 'Dek'],
            datasets: [{
                label: 'Daromad',
                data: [1200, 1900, 800, 1500, 2000, 1800, 2200, 2100, 1600, 1400, 1700, 1900],
                borderColor: 'rgb(16, 185, 129)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Xarajat',
                data: [800, 1200, 600, 1000, 1400, 1200, 1600, 1500, 1100, 900, 1300, 1400],
                borderColor: 'rgb(239, 68, 68)',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
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

    // Categories Chart
    const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
    new Chart(categoriesCtx, {
        type: 'doughnut',
        data: {
            labels: ['Oziq-ovqat', 'Transport', 'O\'yin-kulgi', 'Kommunal', 'Boshqa'],
            datasets: [{
                data: [300, 150, 100, 200, 80],
                backgroundColor: [
                    'rgb(59, 130, 246)',
                    'rgb(16, 185, 129)',
                    'rgb(245, 158, 11)',
                    'rgb(239, 68, 68)',
                    'rgb(156, 163, 175)'
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

function getTransactionTypeBadge(type) {
    switch(type) {
        case 'income': return 'bg-success';
        case 'expense': return 'bg-danger';
        case 'transfer': return 'bg-info';
        default: return 'bg-secondary';
    }
}

function getTransactionTypeIcon(type) {
    switch(type) {
        case 'income': return 'fas fa-plus';
        case 'expense': return 'fas fa-minus';
        case 'transfer': return 'fas fa-exchange-alt';
        default: return 'fas fa-question';
    }
}

function getTransactionTypeText(type) {
    switch(type) {
        case 'income': return 'Daromad';
        case 'expense': return 'Xarajat';
        case 'transfer': return 'O\'tkazma';
        default: return 'Noma\'lum';
    }
}

function exportTransactions() {
    window.open(`/dashboard/users/${userId}/transactions/export`, '_blank');
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

function formatCurrency(amount) {
    return new Intl.NumberFormat('uz-UZ').format(amount) + ' so\'m';
}

function getTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return 'Hozir';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} daqiqa oldin`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} soat oldin`;
    if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)} kun oldin`;
    if (diffInSeconds < 31536000) return `${Math.floor(diffInSeconds / 2592000)} oy oldin`;
    return `${Math.floor(diffInSeconds / 31536000)} yil oldin`;
}

function isUserActive(updatedAt) {
    const lastActivity = new Date(updatedAt);
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);
    return lastActivity >= weekAgo;
}
</script>
@endpush