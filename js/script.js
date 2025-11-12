// Login -=/-=/ Login -=/-=/ Login -=/-=/ Login -=/-=/ Login -=/-=/ Login -=/-=/ Login -=/-=/

document.addEventListener("DOMContentLoaded", function () {
    const loginForm = document.getElementById("loginForm");
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    const googleLoginBtn = document.getElementById("googleLogin");

    // Validação em tempo real dos campos
    [emailInput, passwordInput].forEach(input => {
        input.addEventListener("input", function () {
            if (this.value.trim() !== "") {
                this.classList.remove("invalid");
                this.classList.add("valid");
            } else {
                this.classList.remove("valid");
                this.classList.add("invalid");
            }
        });
    });

    // Validação do formulário antes de enviar
    loginForm.addEventListener("submit", function (e) {
        const emailVal = emailInput.value.trim();
        const passwordVal = passwordInput.value.trim();
        let valid = true;

        // valida e-mail
        if (emailVal === "" || !/\S+@\S+\.\S+/.test(emailVal)) {
            emailInput.classList.add("invalid");
            document.getElementById("email-error").style.display = "block";
            valid = false;
        } else {
            emailInput.classList.remove("invalid");
            document.getElementById("email-error").style.display = "none";
        }

        // valida senha
        if (passwordVal === "") {
            passwordInput.classList.add("invalid");
            document.getElementById("password-error").style.display = "block";
            valid = false;
        } else {
            passwordInput.classList.remove("invalid");
            document.getElementById("password-error").style.display = "none";
        }

        if (!valid) {
            e.preventDefault(); // bloqueia envio se inválido
        }
    });

    // Botão de login com Google (placeholder)
    googleLoginBtn.addEventListener("click", function () {
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Conectando...';
        this.disabled = true;

        setTimeout(() => {
            PetCare.showNotification("Login com Google será implementado em breve!", "info");
            this.innerHTML = '<img src="https://developers.google.com/identity/images/g-logo.png" alt="Google Logo"> Entrar com Google';
            this.disabled = false;
        }, 1500);
    });

    // Animações suaves na entrada
    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = "1";
                entry.target.style.transform = "translateY(0)";
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll(".login-container, .login-header, .login-form, .signup-link")
        .forEach(el => {
            el.style.opacity = "0";
            el.style.transform = "translateY(20px)";
            el.style.transition = "all 0.6s ease-out";
            observer.observe(el);
        });

    // Sistema de notificações (toast)
    window.PetCare = {
        showNotification: function (message, type = "success") {
            const notification = document.createElement("div");
            notification.className = `toast ${type}`;
            notification.innerText = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.add("show");
            }, 10);

            setTimeout(() => {
                notification.classList.remove("show");
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }
    };

    // Exibe notificações vindas do PHP
    if (typeof PHP_ERROR !== "undefined" && PHP_ERROR) {
        PetCare.showNotification(PHP_ERROR, "error");
    }
    if (typeof PHP_SUCCESS !== "undefined" && PHP_SUCCESS) {
        PetCare.showNotification(PHP_SUCCESS, "success");
    }
});
// Login -=/-=/ Login -=/-=/ Login -=/-=/ Login -=/-=/ Login -=/-=/ Login -=/-=/ Login -=/-=/

// Cadastro -=/-=/ Cadastro -=/-=/ Cadastro -=/-=/ Cadastro -=/-=/ Cadastro -=/-=/ Cadastro -=/-=/ 

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('signupForm');
    if (!form) return; // evita erro caso não esteja na página de cadastro

    const inputs = form.querySelectorAll('input, select');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const cpfInput = document.getElementById('cpf');
    const telefoneInput = document.getElementById('telefone');
    const emailInput = document.getElementById('email');
    const datanascInput = document.getElementById('datanasc');
    const generoInput = document.getElementById('genero');
    const googleSignupBtn = document.getElementById('googleSignup');

    // ========== Funções utilitárias ==========
    function showToast(message, type = 'success') {
        if (window.PetCare && PetCare.showNotification) {
            PetCare.showNotification(message, type); // usa o sistema já criado no login
            return;
        }
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    function formatCPF(value) {
        value = value.replace(/\D/g, '');
        if (value.length > 11) value = value.slice(0, 11);
        return value
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    }

    function formatTelefone(value) {
        value = value.replace(/\D/g, '');
        if (value.length > 11) value = value.slice(0, 11);
        if (value.length <= 2) {
            return value;
        } else if (value.length <= 7) {
            return `(${value.slice(0, 2)}) ${value.slice(2)}`;
        } else {
            return `(${value.slice(0, 2)}) ${value.slice(2, 7)}-${value.slice(7)}`;
        }
    }

    function isValidCPF(cpf) {
        cpf = cpf.replace(/\D/g, '');
        if (cpf.length !== 11 || /^(\d)\1+$/.test(cpf)) return false;
        let sum = 0, remainder;
        for (let i = 1; i <= 9; i++) {
            sum += parseInt(cpf[i - 1]) * (11 - i);
        }
        remainder = (sum * 10) % 11;
        if (remainder === 10 || remainder === 11) remainder = 0;
        if (remainder !== parseInt(cpf[9])) return false;
        sum = 0;
        for (let i = 1; i <= 10; i++) {
            sum += parseInt(cpf[i - 1]) * (12 - i);
        }
        remainder = (sum * 10) % 11;
        if (remainder === 10 || remainder === 11) remainder = 0;
        if (remainder !== parseInt(cpf[10])) return false;
        return true;
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function isValidTelefone(telefone) {
        return /^\(\d{2}\)\s\d{5}-\d{4}$/.test(telefone);
    }

    function isValidDate(date) {
        const today = new Date();
        const inputDate = new Date(date);
        return inputDate <= today && inputDate.getFullYear() >= 1900;
    }

    function isValidGenero(genero) {
        return ['Masculino', 'Feminino', 'Outro'].includes(genero);
    }

    function isValidNome(nome) {
        return nome.trim().length >= 2;
    }

    function isValidPassword(password) {
        return password.length >= 8 &&
            /[A-Z]/.test(password) &&
            /[0-9]/.test(password) &&
            /[^A-Za-z0-9]/.test(password);
    }

    // ========== Eventos ==========
    cpfInput.addEventListener('input', function () {
        this.value = formatCPF(this.value);
        const inputGroup = this.parentElement;
        const errorMessage = inputGroup.querySelector('.error-message');
        if (this.value.length === 14) {
            if (isValidCPF(this.value)) {
                inputGroup.classList.remove('invalid');
                inputGroup.classList.add('valid');
                errorMessage.textContent = '';
            } else {
                inputGroup.classList.remove('valid');
                inputGroup.classList.add('invalid');
                errorMessage.textContent = 'CPF inválido.';
            }
        } else {
            inputGroup.classList.remove('valid', 'invalid');
            errorMessage.textContent = '';
        }
    });

    telefoneInput.addEventListener('input', function () {
        const inputGroup = this.parentElement;
        const errorMessage = inputGroup.querySelector('.error-message');
        const cursorPosition = this.selectionStart;
        const oldValue = this.value;
        const oldLength = oldValue.length;
        this.value = formatTelefone(this.value);

        let newCursorPosition = cursorPosition;
        const newValue = this.value;
        const nonDigitCountBefore = (oldValue.slice(0, cursorPosition).match(/\D/g) || []).length;
        const nonDigitCountAfter = (newValue.slice(0, cursorPosition).match(/\D/g) || []).length;
        newCursorPosition += nonDigitCountAfter - nonDigitCountBefore;
        if (newValue.length > oldLength && /[(-)]/.test(newValue[cursorPosition - 1])) {
            newCursorPosition++;
        }
        if (newCursorPosition >= 0 && newCursorPosition <= newValue.length) {
            this.setSelectionRange(newCursorPosition, newCursorPosition);
        }

        if (this.value.length === 15) {
            if (isValidTelefone(this.value)) {
                inputGroup.classList.remove('invalid');
                inputGroup.classList.add('valid');
                errorMessage.textContent = '';
            } else {
                inputGroup.classList.remove('valid');
                inputGroup.classList.add('invalid');
                errorMessage.textContent = 'Telefone inválido. Use o formato (xx) xxxxx-xxxx.';
            }
        } else {
            inputGroup.classList.remove('valid', 'invalid');
            errorMessage.textContent = '';
        }
    });

    generoInput.addEventListener('change', function () {
        const inputGroup = this.parentElement;
        const errorMessage = inputGroup.querySelector('.error-message');
        if (isValidGenero(this.value)) {
            inputGroup.classList.remove('invalid');
            inputGroup.classList.add('valid');
            errorMessage.textContent = '';
        } else {
            inputGroup.classList.remove('valid');
            inputGroup.classList.add('invalid');
            errorMessage.textContent = 'Por favor, selecione um gênero válido.';
        }
    });

    function checkPasswordStrength(password) {
        const strengthBar = document.querySelector('.password-strength-fill');
        const strengthText = document.querySelector('.password-strength-text');
        const strengthContainer = document.querySelector('.password-strength');

        if (password.length > 0) {
            strengthContainer.style.display = 'block';
        } else {
            strengthContainer.style.display = 'none';
            return;
        }

        let strength = 0;
        let feedback = '';
        if (password.length >= 8) strength += 1;
        if (/[a-z]/.test(password)) strength += 1;
        if (/[A-Z]/.test(password)) strength += 1;
        if (/[0-9]/.test(password)) strength += 1;
        if (/[^A-Za-z0-9]/.test(password)) strength += 1;

        strengthBar.className = 'password-strength-fill';
        switch (strength) {
            case 0:
            case 1:
                strengthBar.classList.add('strength-weak');
                feedback = 'Senha muito fraca';
                break;
            case 2:
                strengthBar.classList.add('strength-fair');
                feedback = 'Senha fraca';
                break;
            case 3:
                strengthBar.classList.add('strength-good');
                feedback = 'Senha boa';
                break;
            case 4:
            case 5:
                strengthBar.classList.add('strength-strong');
                feedback = 'Senha forte';
                break;
        }
        strengthText.textContent = feedback;
    }

    window.toggleSenha = function (id) {
        const input = document.getElementById(id);
        const icon = input.nextElementSibling.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    };

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        let isValid = true;

        inputs.forEach(input => {
            const inputGroup = input.parentElement;
            const errorMessage = inputGroup.querySelector('.error-message');
            inputGroup.classList.remove('valid', 'invalid');
            errorMessage.textContent = '';
        });

        inputs.forEach(input => {
            const inputGroup = input.parentElement;
            const errorMessage = inputGroup.querySelector('.error-message');
            if (!input.value && input.required) {
                inputGroup.classList.add('invalid');
                errorMessage.textContent = `Por favor, insira ${input.name}.`;
                isValid = false;
            } else if (input.id === 'nome' && !isValidNome(input.value)) {
                inputGroup.classList.add('invalid');
                errorMessage.textContent = 'Nome deve ter pelo menos 2 caracteres.';
                isValid = false;
            } else if (input.id === 'cpf' && !isValidCPF(input.value)) {
                inputGroup.classList.add('invalid');
                errorMessage.textContent = 'CPF inválido.';
                isValid = false;
            } else if (input.id === 'email' && !isValidEmail(input.value)) {
                inputGroup.classList.add('invalid');
                errorMessage.textContent = 'E-mail inválido.';
                isValid = false;
            } else if (input.id === 'telefone' && !isValidTelefone(input.value)) {
                inputGroup.classList.add('invalid');
                errorMessage.textContent = 'Telefone inválido. Use o formato (xx) xxxxx-xxxx.';
                isValid = false;
            } else if (input.id === 'datanasc' && !isValidDate(input.value)) {
                inputGroup.classList.add('invalid');
                errorMessage.textContent = 'Data de nascimento inválida.';
                isValid = false;
            } else if (input.id === 'genero' && !isValidGenero(input.value)) {
                inputGroup.classList.add('invalid');
                errorMessage.textContent = 'Por favor, selecione um gênero válido.';
                isValid = false;
            } else if (input.id === 'password' && !isValidPassword(input.value)) {
                inputGroup.classList.add('invalid');
                errorMessage.textContent = 'Senha deve ter pelo menos 8 caracteres, incluindo maiúscula, número e símbolo.';
                isValid = false;
            } else {
                inputGroup.classList.add('valid');
            }
        });

        if (passwordInput.value !== confirmPasswordInput.value) {
            confirmPasswordInput.parentElement.classList.add('invalid');
            confirmPasswordInput.parentElement.querySelector('.error-message').textContent = 'As senhas não coincidem.';
            isValid = false;
        } else if (passwordInput.value) {
            confirmPasswordInput.parentElement.classList.add('valid');
        }

        if (isValid) {
            const submitBtn = this.querySelector('.signup-btn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cadastrando...';
            submitBtn.disabled = true;
            this.submit();
        } else {
            showToast('Por favor, corrija os erros no formulário', 'error');
        }
    });

    passwordInput.addEventListener('input', function () {
        const inputGroup = this.parentElement;
        const errorMessage = inputGroup.querySelector('.error-message');
        checkPasswordStrength(this.value);
        if (isValidPassword(this.value)) {
            inputGroup.classList.remove('invalid');
            inputGroup.classList.add('valid');
            errorMessage.textContent = '';
        } else if (this.value) {
            inputGroup.classList.remove('valid');
            inputGroup.classList.add('invalid');
            errorMessage.textContent = 'Senha deve ter pelo menos 8 caracteres, incluindo maiúscula, número e símbolo.';
        } else {
            inputGroup.classList.remove('valid', 'invalid');
            errorMessage.textContent = '';
        }
        if (this.value === confirmPasswordInput.value && this.value) {
            confirmPasswordInput.parentElement.classList.remove('invalid');
            confirmPasswordInput.parentElement.classList.add('valid');
            confirmPasswordInput.parentElement.querySelector('.error-message').textContent = '';
        } else if (confirmPasswordInput.value) {
            confirmPasswordInput.parentElement.classList.add('invalid');
            confirmPasswordInput.parentElement.querySelector('.error-message').textContent = 'As senhas não coincidem.';
        }
    });

    confirmPasswordInput.addEventListener('input', function () {
        const inputGroup = this.parentElement;
        const errorMessage = inputGroup.querySelector('.error-message');
        if (this.value === passwordInput.value && this.value) {
            inputGroup.classList.remove('invalid');
            inputGroup.classList.add('valid');
            errorMessage.textContent = '';
        } else if (this.value) {
            inputGroup.classList.remove('valid');
            inputGroup.classList.add('invalid');
            errorMessage.textContent = 'As senhas não coincidem.';
        } else {
            inputGroup.classList.remove('valid', 'invalid');
            errorMessage.textContent = '';
        }
    });

    emailInput.addEventListener('input', function () {
        const inputGroup = this.parentElement;
        const errorMessage = inputGroup.querySelector('.error-message');
        if (isValidEmail(this.value)) {
            inputGroup.classList.remove('invalid');
            inputGroup.classList.add('valid');
            errorMessage.textContent = '';
        } else if (this.value) {
            inputGroup.classList.remove('valid');
            inputGroup.classList.add('invalid');
            errorMessage.textContent = 'E-mail inválido.';
        } else {
            inputGroup.classList.remove('valid', 'invalid');
            errorMessage.textContent = '';
        }
    });

    datanascInput.addEventListener('input', function () {
        const inputGroup = this.parentElement;
        const errorMessage = inputGroup.querySelector('.error-message');
        if (isValidDate(this.value)) {
            inputGroup.classList.remove('invalid');
            inputGroup.classList.add('valid');
            errorMessage.textContent = '';
        } else if (this.value) {
            inputGroup.classList.remove('valid');
            inputGroup.classList.add('invalid');
            errorMessage.textContent = 'Data de nascimento inválida.';
        } else {
            inputGroup.classList.remove('valid', 'invalid');
            errorMessage.textContent = '';
        }
    });

    googleSignupBtn.addEventListener('click', function () {
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Conectando...';
        this.disabled = true;
        setTimeout(() => {
            showToast('Cadastro com Google será implementado em breve!', 'info');
            this.innerHTML = '<img src="https://developers.google.com/identity/images/g-logo.png" alt="Google Logo"> Cadastrar com Google';
            this.disabled = false;
        }, 1500);
    });
});
// Toggle senha no login
const togglePassword = document.getElementById("togglePassword");
if (togglePassword) {
    togglePassword.addEventListener("click", function () {
        const passwordField = document.getElementById("password");
        const type = passwordField.type === "password" ? "text" : "password";
        passwordField.type = type;

        // troca o ícone
        this.classList.toggle("fa-eye");
        this.classList.toggle("fa-eye-slash");
    });
}

// Cadastro -=/-=/ Cadastro -=/-=/ Cadastro -=/-=/ Cadastro -=/-=/ Cadastro -=/-=/ Cadastro -=/-=/ 


// Animais -=/-=/ Animais -=/-=/ Animais -=/-=/ Animais -=/-=/ Animais -=/-=/ Animais -=/-=/ Animais -=/-=/

function mostrarDetalhes(animal) {
    document.getElementById("sidebar").style.display = "block";
    document.getElementById("detalheNome").textContent = animal.nome;
    document.getElementById("detalheEspecie").textContent = animal.especie;
    document.getElementById("detalheRaca").textContent = animal.raca ?? "Não informado";

    // idade
    if (animal.datanasc) {
        const nasc = new Date(animal.datanasc);
        const hoje = new Date();
        let idade = hoje.getFullYear() - nasc.getFullYear();
        const m = hoje.getMonth() - nasc.getMonth();
        if (m < 0 || (m === 0 && hoje.getDate() < nasc.getDate())) idade--;
        document.getElementById("detalheIdade").textContent = idade + " ano(s)";
    } else {
        document.getElementById("detalheIdade").textContent = "Não informado";
    }

    // agendamentos
    let ag = document.getElementById("detalheAgendamentos");
    ag.innerHTML = "";
    if (animal.agendamentos && animal.agendamentos.length > 0) {
        animal.agendamentos.forEach(a => {
            let li = document.createElement("li");
            li.textContent = `${a.data_hora} ${a.hora_inicio}-${a.hora_final} | ${a.status} | ${a.observacoes}`;
            ag.appendChild(li);
        });
    } else {
        ag.innerHTML = "<li>Nenhum agendamento</li>";
    }

    // consultas
    let co = document.getElementById("detalheConsultas");
    co.innerHTML = "";
    if (animal.consultas && animal.consultas.length > 0) {
        animal.consultas.forEach(c => {
            let li = document.createElement("li");
            li.textContent = `${c.data_consulta} | Diagnóstico: ${c.diagnostico ?? "N/A"}`;
            co.appendChild(li);
        });
    } else {
        co.innerHTML = "<li>Nenhuma consulta</li>";
    }

    // prontuários
    let pr = document.getElementById("detalheProntuarios");
    pr.innerHTML = "";
    if (animal.prontuarios && animal.prontuarios.length > 0) {
        animal.prontuarios.forEach(p => {
            let li = document.createElement("li");
            li.textContent = `${p.data_registro} | ${p.observacoes}`;
            pr.appendChild(li);
        });
    } else {
        pr.innerHTML = "<li>Nenhum prontuário</li>";
    }
}

// Animais -=/-=/ Animais -=/-=/ Animais -=/-=/ Animais -=/-=/ Animais -=/-=/ Animais -=/-=/ Animais -=/-=/

function mostrarDetalhes(animal) {
    document.getElementById("sidebar").style.display = "block";
    document.getElementById("detalheNome").textContent = animal.nome;
    document.getElementById("detalheEspecie").textContent = animal.especie;
    document.getElementById("detalheRaca").textContent = animal.raca ?? "Não informado";

    // idade
    if (animal.datanasc) {
        const nasc = new Date(animal.datanasc);
        const hoje = new Date();
        let idade = hoje.getFullYear() - nasc.getFullYear();
        const m = hoje.getMonth() - nasc.getMonth();
        if (m < 0 || (m === 0 && hoje.getDate() < nasc.getDate())) idade--;
        document.getElementById("detalheIdade").textContent = idade + " ano(s)";
    } else {
        document.getElementById("detalheIdade").textContent = "Não informado";
    }

    // agendamentos
    let ag = document.getElementById("detalheAgendamentos");
    ag.innerHTML = "";
    if (animal.agendamentos && animal.agendamentos.length > 0) {
        animal.agendamentos.forEach(a => {
            let li = document.createElement("li");
            li.textContent = `${a.data_hora} ${a.hora_inicio}-${a.hora_final} | ${a.status} | ${a.observacoes}`;
            ag.appendChild(li);
        });
    } else {
        ag.innerHTML = "<li>Nenhum agendamento</li>";
    }

    // consultas
    let co = document.getElementById("detalheConsultas");
    co.innerHTML = "";
    if (animal.consultas && animal.consultas.length > 0) {
        animal.consultas.forEach(c => {
            let li = document.createElement("li");
            li.textContent = `${c.data_consulta} | Diagnóstico: ${c.diagnostico ?? "N/A"}`;
            co.appendChild(li);
        });
    } else {
        co.innerHTML = "<li>Nenhuma consulta</li>";
    }

    // prontuários
    let pr = document.getElementById("detalheProntuarios");
    pr.innerHTML = "";
    if (animal.prontuarios && animal.prontuarios.length > 0) {
        animal.prontuarios.forEach(p => {
            let li = document.createElement("li");
            li.textContent = `${p.data_registro} | ${p.observacoes}`;
            pr.appendChild(li);
        });
    } else {
        pr.innerHTML = "<li>Nenhum prontuário</li>";
    }
}
// animais pt 2 -=/-=/ animais pt 2 -=/-=/ animais pt 2 -=/-=/ animais pt 2 -=/-=/ animais pt 2 -=/-=/

// Perfil -=/-=/ Perfil -=/-=/ Perfil -=/-=/ Perfil -=/-=/ Perfil -=/-=/ Perfil -=/-=/ Perfil -=/-=/ Perfil -=/-=/



// Perfil -=/-=/ Perfil -=/-=/ Perfil -=/-=/ Perfil -=/-=/ Perfil -=/-=/ Perfil -=/-=/ Perfil -=/-=/ Perfil -=/-=/