@extends('dashboard.layout')

@section('title', 'Foydalanuvchilar')

@section('content')
<div class="page-header">
    <h1 class="page-title">Foydalanuvchilar</h1>
    <p class="page-subtitle">Barcha ro'yxatdan o'tgan foydalanuvchilar ro'yxati</p>
</div>

<!-- Filters and Search -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="search-box">
            <div class="input-group">
                <span class="input-group-text bg-transparent border-0">
                    <i class="fas fa-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-0" id="searchInput" 
                       placeholder="Ism, username yoki Telegram ID bo'yicha qidiring...">
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <select class="form-select" id="statusFilter">
            <option value="">Barcha statuslar</option>
            <option value="active">Faol</option>
            <option value="inactive">Nofaol</option>
        </select>
    </div>
</div>

<!-- Statistics Row -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-number" id="filtered-count">
                <div class="loading"></div>
            </div>
            <div class="stats-label">Topilgan foydalanuvchilar</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-number" id="active-count">
                <div class="loading"></div>
            </div>
            <div class="stats-label">Faol foydalanuvchilar</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-number" id="new-today">
                <div class="loading"></div>
            </div>
            <div class="stats-label">Bugun yangi</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-number" id="new-week">
                <div class="loading"></div>
            </div>
            <div class="stats-label">Bu hafta yangi</div>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-users me-2"></i>
            Foydalanuvchilar ro'yxati
        </h5>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" onclick="exportUsers()">
                <i class="fas fa-download me-1"></i>
                Eksport
            </button>
            <button class="btn btn-primary btn-sm" onclick="refreshUsers()">
                <i class="fas fa-sync-alt me-1"></i>
                Yangilash
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" class="form-check-input" id="selectAll">
                        </th>
                        <th>Foydalanuvchi</th>
                        <th>Telegram ID</th>
                        <th>Ro'yxatdan o'tgan</th>
                        <th>So'nggi faollik</th>
                        <th>Tranzaksiyalar</th>
                        <th>Status</th>
                        <th>Amallar</th>
                    </tr>
                </thead>
                <tbody id="users-table">
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <div class="loading"></div>
                            <div class="mt-2">Ma'lumotlar yuklanmoqda...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <nav aria-label="Sahifalar navigatsiyasi" class="mt-4">
            <ul class="pagination justify-content-center" id="pagination">
                <!-- Pagination will be loaded here -->
            </ul>
        </nav>
    </div>
</div>

<!-- User Actions Modal -->
<div class="modal fade" id="userActionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Foydalanuvchi amallar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" onclick="viewUserDetails()">
                        <i class="fas fa-eye me-2"></i>
                        Ma'lumotlarni ko'rish
                    </button>
                    <button class="btn btn-info" onclick="viewUserTransactions()">
                        <i class="fas fa-exchange-alt me-2"></i>
                        Tranzaksiyalarni ko'rish
                    </button>
                    <button class="btn btn-warning" onclick="toggleUserStatus()">
                        <i class="fas fa-toggle-on me-2"></i>
                        Statusni o'zgartirish
                    </button>
                    <button class="btn btn-danger" onclick="blockUser()">
                        <i class="fas fa-ban me-2"></i>
                        Bloklash
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let currentPage = 1;
let currentSearch = '';
let currentStatus = '';
let selectedUserId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
    loadUserStats();
    
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentSearch = this.value;
            currentPage = 1;
            loadUsers();
        }, 500);
    });
    
    // Status filter
    document.getElementById('statusFilter').addEventListener('change', function() {
        currentStatus = this.value;
        currentPage = 1;
        loadUsers();
    });
    
    // Select all checkbox
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('input[name="userSelect"]');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
});

function loadUsers() {
    const params = new URLSearchParams({
        page: currentPage,
        search: currentSearch,
        status: currentStatus
    });
    
    fetch(`/dashboard/api/users?${params}`)
        .then(response => response.json())
        .then(data => {
            renderUsersTable(data.data);
            renderPagination(data);
            updateFilteredCount(data.total);
        })
        .catch(error => {
            console.error('Error loading users:', error);
            document.getElementById('users-table').innerHTML = 
                '<tr><td colspan="8" class="text-center text-danger">Ma\'lumotlarni yuklashda xatolik yuz berdi</td></tr>';
        });
}

function loadUserStats() {
    fetch('/dashboard/api/user-stats')
        .then(response => response.json())
        .then(data => {
            document.getElementById('active-count').textContent = data.active_count || '0';
            document.getElementById('new-today').textContent = data.new_today || '0';
            document.getElementById('new-week').textContent = data.new_week || '0';
        })
        .catch(error => {
            console.error('Error loading user stats:', error);
        });
}

function renderUsersTable(users) {
    const tbody = document.getElementById('users-table');
    
    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Foydalanuvchilar topilmadi</td></tr>';
        return;
    }
    
    tbody.innerHTML = users.map(user => `
        <tr>
            <td>
                <input type="checkbox" class="form-check-input" name="userSelect" value="${user.id}">
            </td>
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
            <td>
                <div>${formatDate(user.created_at)}</div>
                <small class="text-muted">${getTimeAgo(user.created_at)}</small>
            </td>
            <td>
                <div>${formatDate(user.updated_at)}</div>
                <small class="text-muted">${getTimeAgo(user.updated_at)}</small>
            </td>
            <td>
                <span class="badge bg-info">${user.transactions_count || 0}</span>
            </td>
            <td>
                <span class="badge ${isUserActive(user.updated_at) ? 'bg-success' : 'bg-secondary'}">
                    <i class="fas ${isUserActive(user.updated_at) ? 'fa-check-circle' : 'fa-times-circle'} me-1"></i>
                    ${isUserActive(user.updated_at) ? 'Faol' : 'Nofaol'}
                </span>
            </td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="openUserDetails(${user.id})" title="Ma'lumotlar">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-outline-secondary" onclick="openUserActions(${user.id})" title="Amallar">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function renderPagination(data) {
    const pagination = document.getElementById('pagination');
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
            <a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Oldingi</a>
        </li>`;
    }
    
    // Page numbers
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        paginationHTML += `<li class="page-item ${i === currentPage ? 'active' : ''}">
            <a class="page-link" href="#" onclick="changePage(${i})">${i}</a>
        </li>`;
    }
    
    // Next button
    if (currentPage < totalPages) {
        paginationHTML += `<li class="page-item">
            <a class="page-link" href="#" onclick="changePage(${currentPage + 1})">Keyingi</a>
        </li>`;
    }
    
    pagination.innerHTML = paginationHTML;
}

function changePage(page) {
    currentPage = page;
    loadUsers();
}

function updateFilteredCount(total) {
    document.getElementById('filtered-count').textContent = total;
}

function openUserDetails(userId) {
    window.location.href = `/dashboard/users/${userId}`;
}

function openUserActions(userId) {
    selectedUserId = userId;
    new bootstrap.Modal(document.getElementById('userActionsModal')).show();
}

function viewUserDetails() {
    if (selectedUserId) {
        window.location.href = `/dashboard/users/${selectedUserId}`;
    }
}

function viewUserTransactions() {
    if (selectedUserId) {
        window.location.href = `/dashboard/users/${selectedUserId}#transactions`;
    }
}

function toggleUserStatus() {
    if (selectedUserId) {
        // Implement status toggle functionality
        alert('Status o\'zgartirish funksiyasi ishlab chiqilmoqda...');
    }
}

function blockUser() {
    if (selectedUserId) {
        if (confirm('Haqiqatan ham bu foydalanuvchini bloklashni xohlaysizmi?')) {
            // Implement block functionality
            alert('Bloklash funksiyasi ishlab chiqilmoqda...');
        }
    }
}

function refreshUsers() {
    loadUsers();
    loadUserStats();
}

function exportUsers() {
    // Implement export functionality
    alert('Eksport funksiyasi ishlab chiqilmoqda...');
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