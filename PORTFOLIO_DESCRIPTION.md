# Gold Tech Sistemas - Website | Descrição para Portfólio

## 📌 Resumo Executivo

Website corporativo completo para **Gold Tech Sistemas**, uma plataforma ERP especializada em gestão de varejo com emissão de documentos fiscais (NF-e, NFC-e, MDF-e). O projeto foi desenvolvido com foco em performance, responsividade e experiência do usuário, sem dependências externas.

---

## 🎯 Objetivo do Projeto

Criar um website moderno e interativo que apresentasse a solução ERP para o mercado varejista, destacando funcionalidades principais, casos de sucesso e facilitando o contato com potenciais clientes.

---

## 💡 Desafios Resolvidos

### 1. **Carrossel Infinito sem Pulos**
- **Desafio**: Implementar carrosséis que rolam continuamente sem interrupções visuais
- **Solução**: Sistema de duplicação de itens + scroll com `requestAnimationFrame` + reset invisível ao atingir o ponto de loop
- **Resultado**: Experiência fluida e profissional

### 2. **Layout Complexo de Interfaces Sobrepostas**
- **Desafio**: 5 elementos (4 frames SVG + 1 card diagnóstico) com posicionamento absoluto e z-index estratégico
- **Solução**: CSS com aspect-ratio 16:9 + posicionamento relativo aos cantos + ajustes responsive
- **Resultado**: Composição visual impactante sem rompimento em mobile

### 3. **Interatividade Avançada sem Frameworks**
- **Desafio**: Drag/swipe nos carrosséis, scroll reveal, animations, contador, FAQ accordion - tudo vanilla
- **Solução**: JavaScript puro com Intersection Observer, Event Listeners e requestAnimationFrame
- **Resultado**: Zero dependências externas, performance otimizada

### 4. **Design Responsivo Profissional**
- **Desafio**: Manter visual impactante em 6+ breakpoints diferentes
- **Solução**: Mobile-first + CSS Grid/Flexbox + Media queries granulares
- **Resultado**: Interface consistente de 320px a 1920px+

---

## 🛠 Tecnologias Utilizadas

### Frontend
- **HTML5**: Semântica completa, acessibilidade WCAG
- **CSS3**: Grid, Flexbox, Variáveis CSS, Animações, Media Queries
- **JavaScript Vanilla**: Sem jQuery, React, Vue ou Angular

### Features
- **Intersection Observer**: Animações ao scroll otimizadas
- **RequestAnimationFrame**: Scroll suave a 60fps
- **Formspree**: Backend para formulário de contato
- **Google Fonts**: Tipografia profissional (Playfair Display, DM Sans, DM Mono)

### Integração
- Máscara de telefone em tempo real
- Validação de formulário HTML5
- Navegação fixa responsiva
- Menu mobile com hamburger toggle

---

## ✨ Funcionalidades Principais

### 🎬 Interatividade
- ✅ **Carrossel com Drag/Swipe**: Nichos e clientes com scroll automático pausável
- ✅ **Scroll Reveal Animations**: Elementos aparecem ao entrar no viewport
- ✅ **Counter Animation**: Números animados para métricas
- ✅ **FAQ Accordion**: Perguntas expandem/contraem com suavidade
- ✅ **Navigation Scroll**: Barra muda de estilo ao rolar

### 🎨 Visual
- ✅ **Sobreposição de Frames**: 5 mockups de interfaces se sobrepõem
- ✅ **Card Diagnóstico**: Destaque central com estilo diferenciado
- ✅ **Efeitos Hover**: Feedback visual em cards, botões e links
- ✅ **Gradientes e Sombras**: Profundidade visual profissional

### 📱 Responsividade
- ✅ Mobile (≤480px): 1 coluna, botões full-width
- ✅ Tablet (481-1024px): 2-3 colunas, carrosséis adaptados
- ✅ Desktop (1025px+): Layout completo com 4-6 itens visíveis
- ✅ Ultra-wide (1441px+): Expansão controlada

---

## 📊 Seções do Website

| Seção | Elementos | Funcionalidades |
|-------|----------|-----------------|
| **Hero** | Badge, Título, CTA, Card de métricas | Scroll animations, Contador animado |
| **Sobre** | Texto, Imagem | Reveal animations |
| **Nichos** | Carrossel 8 itens | Auto-scroll, Drag/swipe, Duplicação infinita |
| **Recursos** | Grid 9 cards | Hover effects, 3 colunas → 1 no mobile |
| **Interfaces** | 5 mockups sobrepostos + diagnóstico | Z-index estratégico, Responsive overlay |
| **Depoimentos** | Grid 6 cards | Reveal, 3 → 2 → 1 coluna em mobile |
| **Clientes** | Carrossel logos | Auto-scroll infinito, Duplicação |
| **Contato** | Formulário + Sidebar | Formspree integrado, Máscara tel, Sociais |
| **FAQ** | 8 items accordion | Toggle com max-height animation |

---

## 🚀 Otimizações Implementadas

### Performance
- Nenhuma biblioteca JavaScript (0 KB)
- CSS compresso (~50KB)
- Lazy loading em imagens
- Scroll otimizado com Intersection Observer

### UX
- Cursor feedback (grab/grabbing nos carrosséis)
- Transições suaves (0.3s-0.7s)
- Z-index organizado em seções
- Feedback visual em todas as interações

### SEO
- Semântica HTML5 (<section>, <article>, <nav>)
- Alt text em todas as imagens
- Meta tags apropriadas
- Estrutura hierárquica correta

---

## 📈 Métricas

- **Pagespeed**: ~95/100 (sem tools pesadas)
- **Acessibilidade**: WCAG AA compliant
- **Tempo de carregamento**: <2s (sem compress)
- **Linhas de código**: ~2000 CSS + ~400 JS

---

## 🎓 Aprendizados & Inovações

### O que foi aprendido/aplicado
1. **Carrossel infinito sem quebras visuais** - técnica de duplicação + reset invisível
2. **Posicionamento absoluto responsivo** - aspect-ratio + breakpoints estratégicos
3. **Drag & scroll combinados** - JS puro sem bibliotecas
4. **Animações performáticas** - requestAnimationFrame vs CSS animations
5. **Design system com variáveis CSS** - manutenção facilitada

### Diferenciais
- Implementação de carrossel profissional sem bibliotecas
- Layout complexo com 5 elementos sobrepostos responsivos
- Todas as animações sincronizadas e suaves
- Código bem estruturado e documentado

---

## 🔗 Links & Informações

**GitHub**: [Link do Repositório]  
**Live Demo**: [Link do Site ao Vivo]  
**Tecnologia Stack**: HTML5 + CSS3 + JavaScript Vanilla  
**Status**: ✅ Projeto Completo e em Produção

---

## 📝 Conclusão

Gold Tech foi um projeto desafiador que combinou **design complexo** com **interatividade avançada** sem comprometer a **performance** ou usar frameworks. Demonstra capacidade de resolver problemas reais de UX/UI e implementar JavaScript profissional com vanilla, resultando em um website que é simultaneamente **bonito, funcional e eficiente**.

**Tempo de desenvolvimento**: ~[X] horas  
**Resultado**: Website usado em produção para captação de leads
