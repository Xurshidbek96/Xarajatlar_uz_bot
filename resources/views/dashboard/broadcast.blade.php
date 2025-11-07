@extends('dashboard.layout')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>📢 Broadcast xabarlar</h3>
        <a href="{{ route('dashboard.index') }}" class="btn btn-outline-secondary">⬅️ Dashboard</a>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">Yangi xabar</div>
                <div class="card-body">
                    <form id="broadcastForm" onsubmit="event.preventDefault(); saveMessage();">
                        <div class="mb-3">
                            <label class="form-label">Sarlavha</label>
                            <input type="text" id="title" class="form-control" placeholder="Masalan: Yangilanish" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Xabar matni</label>
                            <textarea id="content" class="form-control" rows="6" placeholder="Xabar matni" required></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">💾 Saqlash (Draft)</button>
                            <button type="button" class="btn btn-success" onclick="sendCurrent()">🚀 Barchaga yuborish</button>
                            <button type="button" class="btn btn-warning" onclick="resetForm()">🔄 Formni tozalash</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Xabarlar ro'yxati</div>
                <div class="card-body">
                    <div id="messagesList"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CKEditor -->
<script src="https://cdn.ckeditor.com/4.20.0/standard/ckeditor.js"></script>
<script>
    let editingId = null;

    function getAuthHeaders() {
        const token = localStorage.getItem('admin_token');
        const headers = { 'Content-Type': 'application/json' };
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        return headers;
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('content')) {
            CKEDITOR.replace('content');
        }
        loadMessages();
    });

    function resetForm() {
        editingId = null;
        document.getElementById('title').value = '';
        if (CKEDITOR.instances['content']) {
            CKEDITOR.instances['content'].setData('');
        } else {
            document.getElementById('content').value = '';
        }
    }

    async function loadMessages() {
        const list = document.getElementById('messagesList');
        list.innerHTML = '<div class="text-muted">Yuklanyapti...</div>';
        try {
            const res = await fetch('/dashboard/api/broadcast-messages', { headers: getAuthHeaders() });
            if (!res.ok) {
                list.innerHTML = '<div class="alert alert-warning">Ro\'yxatni ko\'rish uchun admin token talab qilinadi.</div>';
                return;
            }
            const messages = await res.json();
            if (!Array.isArray(messages)) {
                list.innerHTML = '<div class="alert alert-warning">Ro\'yxatni ko\'rish uchun admin token talab qilinadi.</div>';
                return;
            }
            if (messages.length === 0) {
                list.innerHTML = '<div class="text-muted">Hozircha xabarlar yo\'q.</div>';
                return;
            }
            list.innerHTML = messages.map(m => `
                <div class="border rounded p-2 mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${escapeHtml(m.title)}</strong>
                            <div class="small text-muted">Holat: ${m.status} ${m.sent_at ? ' | Yuborilgan: ' + m.sent_at : ''}</div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="editMessage(${m.id})">✏️ Tahrirlash</button>
                            <button class="btn btn-sm btn-outline-success" onclick="sendMessage(${m.id})">🚀 Yuborish</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteMessage(${m.id})">🗑 O\'chirish</button>
                        </div>
                    </div>
                </div>
            `).join('');
        } catch (e) {
            list.innerHTML = '<div class="alert alert-danger">Xatolik yuz berdi.</div>';
            console.error(e);
        }
    }

    async function saveMessage() {
        const title = document.getElementById('title').value.trim();
        const content = CKEDITOR.instances['content'] ? CKEDITOR.instances['content'].getData() : document.getElementById('content').value.trim();
        if (!title || !content) {
            alert('Sarlavha va xabar matnini kiriting');
            return;
        }
        const method = editingId ? 'PUT' : 'POST';
        const url = editingId ? `/dashboard/api/broadcast-messages/${editingId}` : '/dashboard/api/broadcast-messages';
        try {
            const res = await fetch(url, {
                method,
                headers: getAuthHeaders(),
                body: JSON.stringify({ title, content })
            });
            if (!res.ok) {
                alert('Saqlashda xatolik. Tokenni tekshiring.');
                return;
            }
            editingId = null;
            resetForm();
            loadMessages();
        } catch (e) {
            console.error(e);
            alert('Xatolik yuz berdi.');
        }
    }

    async function editMessage(id) {
        try {
            const res = await fetch(`/dashboard/api/broadcast-messages/${id}`, { headers: getAuthHeaders() });
            if (!res.ok) {
                alert('Ma\'lumotni olishda xatolik.');
                return;
            }
            const m = await res.json();
            editingId = m.id;
            document.getElementById('title').value = m.title || '';
            if (CKEDITOR.instances['content']) {
                CKEDITOR.instances['content'].setData(m.content || '');
            } else {
                document.getElementById('content').value = m.content || '';
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function deleteMessage(id) {
        if (!confirm('Ushbu xabarni o\'chirishni tasdiqlaysizmi?')) return;
        try {
            const res = await fetch(`/dashboard/api/broadcast-messages/${id}`, {
                method: 'DELETE',
                headers: getAuthHeaders()
            });
            if (!res.ok) {
                alert('O\'chirishda xatolik. Tokenni tekshiring.');
                return;
            }
            loadMessages();
        } catch (e) {
            console.error(e);
        }
    }

    async function sendMessage(id) {
        if (!confirm('Ushbu xabarni BARCHA foydalanuvchilarga yuborasizmi?')) return;
        try {
            const res = await fetch(`/dashboard/api/broadcast-messages/${id}/send`, {
                method: 'POST',
                headers: getAuthHeaders()
            });
            if (!res.ok) {
                alert('Yuborishda xatolik. Tokenni tekshiring.');
                return;
            }
            const data = await res.json();
            alert(`Yuborildi: ${data.sent}, xatolik: ${data.failed}`);
            loadMessages();
        } catch (e) {
            console.error(e);
        }
    }

    async function sendCurrent() {
        if (!editingId) {
            // Avval saqlab olish
            await saveMessage();
        }
        if (!editingId) {
            alert('Yangi saqlangan xabarni tanlashda muammo. Ro\'yxatdan yuborishni sinab ko\'ring.');
            return;
        }
        await sendMessage(editingId);
    }

    function escapeHtml(str) {
        return String(str).replace(/[&<>"]+/g, function(s) {
            const entityMap = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' };
            return entityMap[s] || s;
        });
    }
</script>
@endsection