# 🏢 Gold Tech Sistemas - Website

Bem-vindo ao repositório do website da **Gold Tech Sistemas**, uma solução ERP completa para varejo com emissão de NF-e, NFC-e e MDF-e.

![Status](https://img.shields.io/badge/Status-Ativo-brightgreen)
![License](https://img.shields.io/badge/License-MIT-blue)
![HTML5](https://img.shields.io/badge/HTML5-E34C26?logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?logo=javascript&logoColor=black)

---

## ✨ Características

- 🎨 **Design Moderno**: Interface clean e profissional
- 📱 **Totalmente Responsivo**: Funciona perfeitamente em mobile, tablet e desktop
- ⚡ **Performance**: Sem dependências externas, 100% vanilla HTML/CSS/JS
- 🎯 **Interativo**: Animações suaves, carrosséis com drag/swipe, scroll reveal
- 📧 **Formulário Funcional**: Integração com Formspree para recebimento de emails
- ♿ **Acessível**: Semântica HTML5 adequada
- 🎬 **Animações**: Scroll animations, carousel automático, hover effects

---

## 🚀 Seções Incluídas

| Seção | Descrição |
|-------|-----------|
| 🏠 **Hero** | Banner principal com chamada para ação |
| 📋 **Recursos** | Grid de features do ERP |
| 👥 **Sobre** | Histórico e valores da empresa |
| 🎯 **Nichos** | Carrossel com segmentos de negócio |
| 💬 **Depoimentos** | Grid de testimonials de clientes |
| ❓ **FAQ** | Accordion com perguntas frequentes |
| 📞 **Contato** | Formulário + informações de contato |
| 👔 **Clientes** | Carousel com logos de clientes |

---

## 📁 Estrutura do Projeto

```
Site Patrick/
├── 📄 index.html           # Arquivo principal (HTML semântico)
├── 🎨 css/
│   └── styles.css          # Todos os estilos (1700+ linhas)
├── ⚙️ js/
│   └── script.js           # Lógica JavaScript (vanilla)
├── 🖼️ assets/              # Imagens, logos e favicon
│   ├── logo.png
│   ├── favicon.ico
│   ├── eu.jpeg
│   └── svg/
│       ├── padaria.svg
│       ├── restaurante.svg
│       └── ... (mais SVGs)
└── 📖 README.md            # Este arquivo
```

---

## 🎨 Paleta de Cores

```css
--gold:        #c9a227    /* Ouro principal */
--gold-light:  #e8c45a    /* Ouro claro */
--bg:          #060608    /* Fundo escuro */
--surface:     #0c0c10    /* Surface 1 */
--text:        #f4f2ed    /* Texto principal */
--text-soft:   #8a8880    /* Texto secundário */
```

---

## 💻 Funcionalidades JavaScript

### Menu Mobile
```javascript
toggleMenu()  // Abre/fecha menu responsivo
```

### Animações
- **Scroll Reveal**: Elementos aparecem com animação ao entrar em viewport
- **Counter Animation**: Números animados para métricas
- **Carrossel Automático**: Nichos e clientes rolam continuamente
- **Drag/Swipe**: Usuários podem arrastar carrosséis em desktop/mobile

### Formulário
- **Máscara de Telefone**: Formata automaticamente números de telefone
- **Integração Formspree**: Recebe emails diretamente
- **Validação**: Campos obrigatórios funcionam via HTML5

### Efeitos Visuais
- **Navigation Scroll**: Muda cor da barra ao rolar
- **FAQ Accordion**: Abre/fecha perguntas
- **Hover Effects**: Feedback visual em elementos interativos

---

## 🔧 Tecnologias

| Tecnologia | Uso |
|------------|-----|
| **HTML5** | Estrutura semântica |
| **CSS3** | Grid, Flexbox, Animações, Variáveis CSS |
| **JavaScript (Vanilla)** | Interatividade, sem frameworks |
| **Google Fonts** | Tipografia (Playfair Display, DM Sans, DM Mono) |
| **Formspree** | Backend para formulário |
| **Intersection Observer** | Scroll animations otimizadas |

---

## 📱 Breakpoints Responsivos

| Dispositivo | Largura | Layout |
|------------|---------|--------|
| 📱 Mobile | ≤480px | 1 coluna, botões full-width |
| 📱 Tablet Pequeno | 481-600px | 1-2 colunas |
| 📱 Tablet | 601-768px | 2 colunas |
| 💻 Tablet Grande | 769-1024px | 2-3 colunas |
| 🖥️ Desktop | 1025-1440px | Layout completo |
| 🖥️ Grande Desktop | 1441px+ | Layout expandido |

---

## ⚙️ Configuração

### 1️⃣ Clonar o Repositório
```bash
git clone https://github.com/seu-usuario/gold-tech-website.git
cd "Site Patrick"
```

### 2️⃣ Abrir Localmente
```bash
# Opção 1: Abrir arquivo diretamente
open index.html

# Opção 2: Usar Live Server (recomendado)
npx live-server
```

### 3️⃣ Configurar Formspree
1. Acesse [formspree.io](https://formspree.io)
2. Crie uma nova conta
3. Obtenha seu ID do formulário
4. Substitua em `index.html`:
```html
<form action="https://formspree.io/f/SEU_ID_AQUI" method="POST">
```

---

## 📝 Customização

### Cores
Edite as variáveis CSS em `css/styles.css`:
```css
:root {
  --gold: #c9a227;        /* Altere a cor principal */
  --bg: #060608;          /* Altere o fundo */
  /* ... outras cores */
}
```

### Textos
Edite direto em `index.html` as seções:
- Hero title
- About section
- Features
- Testimonials
- FAQ
- Contact info

### Velocidade dos Carrosséis
Em `css/styles.css`, altere a duração da animação:
```css
animation: scrollCarousel 25s linear infinite;  /* Reduza para mais rápido */
```

### Logos e Imagens
Coloque seus arquivos em `assets/`:
- `logo.png` - Logo principal
- `favicon.ico` - Ícone do navegador
- `eu.jpeg` - Foto do fundador
- SVGs para nichos e clientes

---

## 🎯 Performance

- ✅ Sem JavaScript pesado
- ✅ CSS otimizado (~1700 linhas)
- ✅ Imagens otimizadas para web
- ✅ Lazy loading ativado
- ✅ Scrollbar customizado
- ✅ Sem frameworks externos

---

## 📧 Contato & Suporte

- **Email**: goldtechsistemas@outlook.com
- **WhatsApp**: (31) 99955-4328
- **Endereço**: Sete Lagoas, MG
- **Horário**: Seg-Sex, 08h às 18h

---

## 📄 Licença

Este projeto está sob a licença MIT. Veja o arquivo `LICENSE` para detalhes.

---

## 🙏 Agradecimentos

- Google Fonts pela tipografia
- Formspree pelos serviços de formulário
- Comunidade open source

---

## 📈 Roadmap

- [ ] Blog integrado
- [ ] Seção de pricing
- [ ] Chat de suporte ao vivo
- [ ] Integração com CRM
- [ ] Multilíngue (PT/EN)
- [ ] PWA (Progressive Web App)

---

<div align="center">

**Feito com ❤️ para Gold Tech Sistemas**

![Last Updated](https://img.shields.io/badge/Last%20Updated-May%202026-blue)

</div>
