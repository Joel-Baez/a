const API_USERS = 'http://127.0.0.1:8001';

// Mostrar mensaje
function showMessage(message, type = 'success') {
    const messageDiv = document.getElementById('message');
    messageDiv.textContent = message;
    messageDiv.className = `message ${type} show`;
    
    setTimeout(() => {
        messageDiv.classList.remove('show');
    }, 5000);
}

// Verificar si ya hay sesión iniciada
function checkSession() {
    const token = localStorage.getItem('token');
    const user = JSON.parse(localStorage.getItem('user') || 'null');
    
    if (token && user) {
        // Redirigir según el rol
        if (user.role === 'admin') {
            window.location.href = 'admin.html';
        } else {
            window.location.href = 'gestor.html';
        }
    }
}

// Ejecutar al cargar la página de login, registro o la landing index
if (
    window.location.pathname.includes('login.html') ||
    window.location.pathname.includes('register.html') ||
    window.location.pathname.endsWith('/frontend/') ||
    window.location.pathname.includes('index.html')
) {
    checkSession();
}

// Manejar formulario de registro
const registerForm = document.getElementById('registerForm');
if (registerForm) {
    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = {
            name: document.getElementById('name').value,
            email: document.getElementById('email').value,
            password: document.getElementById('password').value,
            role: document.getElementById('role').value
        };
        
        try {
            const response = await fetch(`${API_USERS}/register`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                showMessage('Registro exitoso. Redirigiendo al login...', 'success');
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 2000);
            } else {
                showMessage(data.message, 'error');
            }
        } catch (error) {
            showMessage('Error al conectar con el servidor', 'error');
            console.error('Error:', error);
        }
    });
}

// Manejar formulario de login
const loginForm = document.getElementById('loginForm');
if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = {
            email: document.getElementById('email').value,
            password: document.getElementById('password').value
        };
        
        try {
            const response = await fetch(`${API_USERS}/login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Guardar token y datos del usuario
                localStorage.setItem('token', data.token);
                localStorage.setItem('user', JSON.stringify(data.user));
                
                showMessage('Inicio de sesión exitoso. Redirigiendo...', 'success');
                
                // Redirigir según el rol
                setTimeout(() => {
                    if (data.user.role === 'admin') {
                        window.location.href = 'admin.html';
                    } else {
                        window.location.href = 'gestor.html';
                    }
                }, 1000);
            } else {
                showMessage(data.message, 'error');
            }
        } catch (error) {
            showMessage('Error al conectar con el servidor', 'error');
            console.error('Error:', error);
        }
    });
}