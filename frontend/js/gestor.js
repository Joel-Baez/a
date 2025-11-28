const API_USERS = 'http://127.0.0.1:8003';
const API_TICKETS = 'http://127.0.0.1:8002';

let currentTickets = [];
let selectedTicket = null;

// Verificar autenticación
function checkAuth() {
    const token = localStorage.getItem('token');
    const user = JSON.parse(localStorage.getItem('user') || 'null');
    
    if (!token || !user) {
        window.location.href = 'login.html';
        return null;
    }
    
    if (user.role !== 'gestor') {
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

// Crear ticket
document.getElementById('createTicketForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = {
        titulo: document.getElementById('titulo').value,
        descripcion: document.getElementById('descripcion').value
    };
    
    try {
        const response = await fetch(`${API_TICKETS}/tickets`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${auth.token}`
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Ticket creado exitosamente', 'success');
            document.getElementById('createTicketForm').reset();
            loadTickets();
        } else {
            showMessage(data.message, 'error');
        }
    } catch (error) {
        showMessage('Error al crear el ticket', 'error');
        console.error('Error:', error);
    }
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
                <div>Creado: ${formatDate(ticket.created_at)}</div>
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
    
    modalBody.innerHTML = `
        <div class="ticket-details">
            <div class="ticket-info">
                <div class="info-item">
                    <label>Estado</label>
                    <span class="ticket-status ${ticket.estado}">${formatEstado(ticket.estado)}</span>
                </div>
                <div class="info-item">
                    <label>Creado por</label>
                    <span>${ticket.gestor.name}</span>
                </div>
                <div class="info-item">
                    <label>Fecha de creación</label>
                    <span>${formatDate(ticket.created_at)}</span>
                </div>
                <div class="info-item">
                    <label>Asignado a</label>
                    <span>${ticket.admin ? ticket.admin.name : 'Sin asignar'}</span>
                </div>
            </div>
            
            <div class="info-item" style="margin-bottom: 1.5rem;">
                <label>Descripción</label>
                <p style="margin-top: 0.5rem;">${ticket.descripcion}</p>
            </div>
            
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

// Cerrar modal
document.querySelector('.close').addEventListener('click', () => {
    document.getElementById('ticketModal').classList.remove('show');
});

// Filtrar por estado
document.getElementById('filterEstado').addEventListener('change', (e) => {
    loadTickets(e.target.value);
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