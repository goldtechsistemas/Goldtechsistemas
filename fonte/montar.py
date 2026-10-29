"""Monta o index.html do site a partir de fonte/pagina.html.

Uso (na pasta do repositório):  python fonte/montar.py

Preenche os trechos {{...}} da página:
  {{wa}}           link do WhatsApp com a mensagem pronta
  {{segs}}         os 8 blocos de segmentos (lista SEGMENTOS abaixo)
  {{logos}}        a faixa de logos de clientes (img/cli1.webp a img/cli15.webp)
  {{p_painel_set}} as versões do painel para celular (img/p_painel-960.webp e -1280.webp)
  {{shot_sizes}}   o tamanho das telas do sistema na página
  {{qualquer}}     vira img/qualquer.webp (o arquivo precisa existir na pasta img/)
Depois impede que palavras com hífen (NF-e, e-mail, CNPJ, telefone) quebrem no meio da linha
e confere se nenhuma tag ficou aberta. Só usa o Python, sem instalar nada.
"""
import os, re, sys
from html.parser import HTMLParser

AQUI = os.path.dirname(os.path.abspath(__file__))
RAIZ = os.path.dirname(AQUI)
html = open(os.path.join(AQUI, "pagina.html"), encoding="utf-8").read()

WA = ("https://wa.me/5531995544328?text="
      "Ol%C3%A1%2C%20vim%20do%20site%20e%20queria%20saber%20mais%20sobre%20o%20sistema%20fiscal.")

# imagem, opção do formulário, nome, texto do botão
SEGMENTOS = [("padaria", "Padaria", "Padarias", "Ver para padarias"),
             ("restaurante", "Restaurante", "Restaurantes", "Ver para restaurantes"),
             ("roupa", "Loja de roupas", "Lojas de roupas", "Ver para lojas de roupas"),
             ("acougues", "Açougue", "Açougues", "Ver para açougues"),
             ("oticas", "Ótica", "Óticas", "Ver para óticas"),
             ("material", "Material de construção", "Material de construção", "Ver para construção"),
             ("hortifruti", "Hortifrúti", "Hortifrúti", "Ver para hortifrúti"),
             ("transportadora", "Transportadora", "Transportadoras", "Ver para transportadoras")]

def img(nome):
    caminho = os.path.join(RAIZ, "img", nome + ".webp")
    if not os.path.exists(caminho):
        sys.exit(f"Falta a imagem img/{nome}.webp")
    return f"img/{nome}.webp"

seta = ('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" '
        'stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>')
segs = "\n        ".join(
    f'<a class="seg-tile" href="#demonstracao" data-seg="{opcao}" aria-label="{nome}: agendar demonstração">'
    f'<img src="{img(chave)}" width="360" height="450" alt="" loading="lazy" decoding="async">'
    f'<span class="seg-go"><span>{texto}</span>{seta}</span></a>'
    for chave, opcao, nome, texto in SEGMENTOS)

logos = "".join(f'<img src="{img(f"cli{i}")}" width="64" height="64" alt="" loading="lazy" decoding="async">' for i in range(1, 16))
logos += '<span aria-hidden="true" style="display:contents">' + logos + "</span>"  # repetidos: a faixa gira sem emenda

especiais = {
    "wa": WA,
    "segs": segs,
    "logos": logos,
    "p_painel_set": f"{img('p_painel-960')} 960w, {img('p_painel-1280')} 1280w, {img('p_painel')} 1918w",
    "shot_sizes": "(max-width: 720px) calc(100vw - 32px), (max-width: 1224px) calc(100vw - 64px), 1160px",
}
html = re.sub(r"\{\{([a-z0-9_-]+)\}\}", lambda m: especiais.get(m.group(1)) or img(m.group(1)), html)

# Palavras com hífen não quebram no meio; só mexe no texto visível, fora de tags e scripts.
PULAR = re.compile(r"<(script|style|pre|code|title|textarea|select|svg)\b.*?</\1>", re.S | re.I)
TAG = re.compile(r"(<!--.*?-->|<[^>]*>)", re.S)
PALAVRA = re.compile(r"\(\d{2}\) \d \d{4}-\d{4}|[0-9A-Za-zÀ-ÿ./]*[0-9A-Za-zÀ-ÿ]-[0-9A-Za-zÀ-ÿ][0-9A-Za-zÀ-ÿ./-]*")
def sem_quebra(trecho):
    partes = TAG.split(trecho)
    partes[::2] = [PALAVRA.sub(r'<span class="nw">\g<0></span>', p) for p in partes[::2]]
    return "".join(partes)
pedacos, pos = [], 0
for m in PULAR.finditer(html):
    pedacos += [sem_quebra(html[pos:m.start()]), m.group(0)]
    pos = m.end()
html = "".join(pedacos) + sem_quebra(html[pos:])

VAZIAS = {"area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "source", "track", "wbr"}
class Conferir(HTMLParser):
    def __init__(self):
        super().__init__(); self.abertas, self.erros = [], []
    def handle_starttag(self, tag, attrs):
        if tag not in VAZIAS: self.abertas.append((tag, self.getpos()[0]))
    def handle_endtag(self, tag):
        if self.abertas and self.abertas[-1][0] == tag: self.abertas.pop()
        else: self.erros.append(f"linha {self.getpos()[0]}: </{tag}> fechando {self.abertas[-1] if self.abertas else 'nada'}")
c = Conferir(); c.feed(html)
if c.erros or c.abertas:
    sys.exit(f"Tags com problema: {c.erros[:5]} abertas: {c.abertas[:5]}")
if "{{" in html:
    sys.exit("Ficou algum {{...}} sem preencher")

open(os.path.join(RAIZ, "index.html"), "w", encoding="utf-8").write(html)
print(f"index.html montado: {len(html.encode()) / 1024:.0f} KB")
