const API_USERS = 'http://127.0.0.1:8003';
const API_TICKETS = 'http://127.0.0.1:8002';

let currentTickets = [];
let currentUsers = [];
let selectedTicket = null;

// Verificar autenticación
function checkAuth() {
    const token = localStorage.getItem('token');
    const user = JSON.parse(localStorage.getItem('user') || 'null');
    
    if (!token || !user) {
        window.location.href = 'login.html';
        return null;
    }
    
    if (user.role !== 'admin') {
        window.location.href = 'login.html';
        return null;
    }
    
    return { token, user };
}

// Mostrar mensaje
function showMessage(message, type = 'success') {
    const messageDiv = document.getElementById('message');
    messageDiv.textContent = message;
    messageDiv.className = `message ${type} show`;
    
    setTimeout(() => {
        messageDiv.classList.remove('show');
    }, 5000);
}

// Inicializar página
const auth = checkAuth();
if (auth) {
    document.getElementById('userName').textContent = auth.user.name;
    loadTickets();
    loadUsers();
}

// Cerrar sesión
document.getElementById('logoutBtn').addEventListener('click', async () => {
    try {
        await fetch(`${API_USERS}/logout`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${auth.token}`
            }
        });
    } catch (error) {
        console.error('Error al cerrar sesión:', error);
    }
    
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = 'login.html';
});

// Manejar tabs
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tabName = btn.dataset.tab;
        
        // Remover clase active de todos los botones y contenidos
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        // Agregar clase active al botón y contenido seleccionado
        btn.classList.add('active');
        document.getElementById(`${tabName}Tab`).classList.add('active');
    });
});

// Cargar tickets
async function loadTickets(estado = '') {
    try {
        let url = `${API_TICKETS}/tickets`;
        if (estado) {
            url += `?estado=${estado}`;
        }
        
        const response = await fetch(url, {
            headers: {
                'Authorization': `Bearer ${auth.token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentTickets = data.tickets;
            renderTickets(data.tickets);
        }
    } catch (error) {
        showMessage('Error al cargar los tickets', 'error');
        console.error('Error:', error);
    }
}

// Renderizar tickets
function renderTickets(tickets) {
    const ticketsList = document.getElementById('ticketsList');
    
    if (tickets.length === 0) {
        ticketsList.innerHTML = '<p style="text-align: center; color: #64748b; padding: 2rem;">No hay tickets para mostrar</p>';
        return;
    }
    
    ticketsList.innerHTML = tickets.map(ticket => `
        <div class="ticket-card" onclick="viewTicketDetails(${ticket.id})">
            <div class="ticket-card-header">
                <h3>${ticket.titulo}</h3>
                <span class="ticket-status ${ticket.estado}">${formatEstado(ticket.estado)}</span>
            </div>
            <p>${ticket.descripcion.substring(0, 100)}${ticket.descripcion.length > 100 ? '...' : ''}</p>
            <div class="ticket-card-footer">
                <div>Creado por: ${ticket.gestor.name}</div>
                <div>Fecha: ${formatDate(ticket.created_at)}</div>
                ${ticket.admin ? `<div>Asignado a: ${ticket.admin.name}</div>` : '<div>Sin asignar</div>'}
            </div>
        </div>
    `).join('');
}

// Ver detalles del ticket
async function viewTicketDetails(ticketId) {
    try {
        const response = await fetch(`${API_TICKETS}/tickets/${ticketId}`, {
            headers: {
                'Authorization': `Bearer ${auth.token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            selectedTicket = data.ticket;
            showTicketModal(data.ticket);
        }
    } catch (error) {
        showMessage('Error al cargar los detalles', 'error');
        console.error('Error:', error);
    }
}

// Mostrar modal con detalles
function showTicketModal(ticket) {
    const modal = document.getElementById('ticketModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    
    modalTitle.textContent = ticket.titulo;
    
    // Obtener lista de admins para el select
    const adminOptions = currentUsers
        .filter(u => u.role === 'admin')
        .map(u => `<option value="${u.id}" ${ticket.admin_id === u.id ? 'selected' : ''}>${u.name}</option>`)
        .join('');
    
    modalBody.innerHTML = `
        <div class="ticket-details">
            <div class="ticket-info">
                <div class="info-item">
                    <label>Creado por</label>
                    <span>${ticket.gestor.name}</span>
                </div>
                <div class="info-item">
                    <label>Fecha de creación</label>
                    <span>${formatDate(ticket.created_at)}</span>
                </div>
            </div>
            
            <div class="info-item" style="margin-bottom: 1.5rem;">
                <label>Descripción</label>
                <p style="margin-top: 0.5rem;">${ticket.descripcion}</p>
            </div>
            
            <form id="updateTicketForm" onsubmit="updateTicket(event, ${ticket.id})">
                <div class="form-group">
                    <label>Estado</label>
                    <select id="ticketEstado" required>
                        <option value="abierto" ${ticket.estado === 'abierto' ? 'selected' : ''}>Abierto</option>
                        <option value="en_progreso" ${ticket.estado === 'en_progreso' ? 'selected' : ''}>En Progreso</option>
                        <option value="resuelto" ${ticket.estado === 'resuelto' ? 'selected' : ''}>Resuelto</option>
                        <option value="cerrado" ${ticket.estado === 'cerrado' ? 'selected' : ''}>Cerrado</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Asignar a</label>
                    <select id="ticketAdmin">
                        <option value="">Sin asignar</option>
                        ${adminOptions}
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Actualizar Ticket</button>
            </form>
            
            <div class="comments-section">
                <h3>Historial de Actividad</h3>
                <div id="commentsList">
                    ${ticket.actividades.map(act => `
                        <div class="comment">
                            <div class="comment-header">
                                <span class="comment-author">${act.user.name}</span>
                                <span class="comment-date">${formatDate(act.created_at)}</span>
                            </div>
                            <div class="comment-message">${act.mensaje}</div>
                        </div>
                    `).join('')}
                </div>
                
                <div class="add-comment-form">
                    <h4>Agregar Comentario</h4>
                    <form id="addCommentForm" onsubmit="addComment(event, ${ticket.id})">
                        <div class="form-group">
                            <textarea id="commentMessage" rows="3" placeholder="Escribe tu comentario..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Agregar Comentario</button>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    modal.classList.add('show');
}

// Actualizar ticket
async function updateTicket(e, ticketId) {
    e.preventDefault();
    
    const formData = {
        estado: document.getElementById('ticketEstado').value,
        admin_id: document.getElementById('ticketAdmin').value || null
    };
    
    try {
        const response = await fetch(`${API_TICKETS}/tickets/${ticketId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${auth.token}`
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Ticket actualizado exitosamente', 'success');
            loadTickets();
            viewTicketDetails(ticketId); // Recargar detalles
        } else {
            showMessage(data.message, 'error');
        }
    } catch (error) {
        showMessage('Error al actualizar el ticket', 'error');
        console.error('Error:', error);
    }
}

// Agregar comentario
async function addComment(e, ticketId) {
    e.preventDefault();
    
    const mensaje = document.getElementById('commentMessage').value;
    
    try {
        const response = await fetch(`${API_TICKETS}/tickets/${ticketId}/comments`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${auth.token}`
            },
            body: JSON.stringify({ mensaje })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Comentario agregado', 'success');
            viewTicketDetails(ticketId); // Recargar detalles
        } else {
            showMessage(data.message, 'error');
        }
    } catch (error) {
        showMessage('Error al agregar comentario', 'error');
        console.error('Error:', error);
    }
}

// Cerrar modal de ticket
document.getElementById('closeTicketModal').addEventListener('click', () => {
    document.getElementById('ticketModal').classList.remove('show');
});

// Filtrar por estado
document.getElementById('filterEstado').addEventListener('change', (e) => {
    loadTickets(e.target.value);
});

// === GESTIÓN DE USUARIOS ===

// Cargar usuarios
async function loadUsers() {
    try {
        const response = await fetch(`${API_USERS}/users`, {
            headers: {
                'Authorization': `Bearer ${auth.token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentUsers = data.users;
            renderUsers(data.users);
        }
    } catch (error) {
        showMessage('Error al cargar los usuarios', 'error');
        console.error('Error:', error);
    }
}

// Renderizar usuarios
function renderUsers(users) {
    const tbody = document.querySelector('#usersTable tbody');
    
    tbody.innerHTML = users.map(user => `
        <tr>
            <td>${user.id}</td>
            <td>${user.name}</td>
            <td>${user.email}</td>
            <td>${user.role === 'admin' ? 'Administrador' : 'Gestor'}</td>
            <td>
                <button class="btn btn-edit" onclick="editUser(${user.id})">Editar</button>
                <button class="btn btn-danger" onclick="deleteUser(${user.id})">Eliminar</button>
            </td>
        </tr>
    `).join('');
}

// Editar usuario
function editUser(userId) {
    const user = currentUsers.find(u => u.id === userId);
    if (!user) return;
    
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editName').value = user.name;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editRole').value = user.role;
    
    document.getElementById('userModal').classList.add('show');
}

// Actualizar usuario
document.getElementById('editUserForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const userId = document.getElementById('editUserId').value;
    const formData = {
        name: document.getElementById('editName').value,
        email: document.getElementById('editEmail').value,
        role: document.getElementById('editRole').value
    };
    
    try {
        const response = await fetch(`${API_USERS}/users/${userId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${auth.token}`
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Usuario actualizado exitosamente', 'success');
            document.getElementById('userModal').classList.remove('show');
            loadUsers();
        } else {
            showMessage(data.message, 'error');
        }
    } catch (error) {
        showMessage('Error al actualizar el usuario', 'error');
        console.error('Error:', error);
    }
});

// Eliminar usuario
async function deleteUser(userId) {
    if (!confirm('¿Estás seguro de eliminar este usuario?')) return;
    
    try {
        const response = await fetch(`${API_USERS}/users/${userId}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${auth.token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Usuario eliminado exitosamente', 'success');
            loadUsers();
        } else {
            showMessage(data.message, 'error');
        }
    } catch (error) {
        showMessage('Error al eliminar el usuario', 'error');
        console.error('Error:', error);
    }
}

// Cerrar modal de usuario
document.getElementById('closeUserModal').addEventListener('click', () => {
    document.getElementById('userModal').classList.remove('show');
});

// Funciones auxiliares
function formatEstado(estado) {
    const estados = {
        'abierto': 'Abierto',
        'en_progreso': 'En Progreso',
        'resuelto': 'Resuelto',
        'cerrado': 'Cerrado'
    };
    return estados[estado] || estado;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('es-ES', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}