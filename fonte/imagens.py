"""Converte uma imagem para WebP na pasta img/, com opção de desfocar áreas com dados de clientes.

Uso (na pasta do repositório):
  python fonte/imagens.py caminho/do/print.png p_nome [--largura 1600] [--desfocar x0,y0,x1,y1 ...] [--celular]

  p_nome      nome do arquivo final, sem extensão: vira img/p_nome.webp e {{p_nome}} na página
  --largura   reduz a imagem se ela for mais larga que isso
  --desfocar  área a desfocar, em pixels da imagem original (esquerda,topo,direita,baixo); pode repetir
  --celular   gera também versões de 960 e 1280 px para celular (só vale para imagens bem largas)

Precisa do Pillow:  pip install pillow
O repositório é público: nunca coloque aqui um print com dados de clientes sem desfocar.
"""
import argparse, io, os
from PIL import Image, ImageFilter

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

p = argparse.ArgumentParser()
p.add_argument("entrada")
p.add_argument("nome")
p.add_argument("--largura", type=int)
p.add_argument("--desfocar", action="append", default=[])
p.add_argument("--celular", action="store_true")
a = p.parse_args()

im = Image.open(a.entrada)
im = im.convert("RGBA" if im.mode == "RGBA" and im.getextrema()[3][0] < 255 else "RGB")
for caixa in a.desfocar:
    box = tuple(int(v) for v in caixa.split(","))
    area = im.crop(box)
    w, h = area.size
    area = area.resize((max(1, w // 7), max(1, h // 7)), Image.BILINEAR).resize((w, h), Image.BILINEAR)
    im.paste(area.filter(ImageFilter.GaussianBlur(2)), box)
if a.largura and im.width > a.largura:
    im = im.resize((a.largura, round(im.height * a.largura / im.width)), Image.LANCZOS)

# Telas de sistema costumam ficar menores sem perda; fotos, com perda leve. Fica o menor dos dois.
versoes = {}
for tipo, opcoes in (("sem perda", dict(lossless=True, quality=100, method=6)), ("com perda", dict(quality=86, method=6))):
    buf = io.BytesIO()
    im.save(buf, "WEBP", **opcoes)
    versoes[tipo] = buf.getvalue()
tipo = "sem perda" if len(versoes["sem perda"]) <= 1.25 * len(versoes["com perda"]) else "com perda"
destino = os.path.join(RAIZ, "img", a.nome + ".webp")
open(destino, "wb").write(versoes[tipo])
print(f"img/{a.nome}.webp  {im.width}x{im.height}  {tipo}  {len(versoes[tipo]) / 1024:.0f} KB")

if a.celular:
    for w in (960, 1280):
        if w < 0.8 * im.width:
            menor = im.resize((w, round(im.height * w / im.width)), Image.LANCZOS)
            menor.save(os.path.join(RAIZ, "img", f"{a.nome}-{w}.webp"), "WEBP", quality=84, method=6)
            print(f"img/{a.nome}-{w}.webp")
