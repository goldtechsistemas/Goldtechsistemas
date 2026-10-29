"""Cifra os links da área de downloads do rodapé com uma senha e grava em fonte/pagina.html.

Uso (na pasta do repositório):  python fonte/downloads.py lista.txt
  lista.txt: um link por linha, no formato   Nome | https://...
  A senha é pedida no terminal, duas vezes (ou vem da variável DOWNLOADS_SENHA).
  Depois rode  python fonte/montar.py  para gerar o index.html.

Os links ficam embaralhados (AES-GCM, chave tirada da senha com PBKDF2): sem a senha,
nem quem lê o código da página ou o GitHub consegue ver. O mesmo esquema é desfeito
no navegador quando o técnico digita a senha.

Precisa do pacote cryptography:  pip install cryptography
Nunca coloque a lista nem a senha no repositório (ele é público).
"""
import base64, getpass, json, os, re, sys
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.ciphers.aead import AESGCM
from cryptography.hazmat.primitives.kdf.pbkdf2 import PBKDF2HMAC

ITERACOES = 600_000  # igual ao valor usado em pagina.html na hora de abrir
PAGINA = os.path.join(os.path.dirname(os.path.abspath(__file__)), "pagina.html")

if len(sys.argv) != 2:
    sys.exit(__doc__)
itens = []
for linha in open(sys.argv[1], encoding="utf-8"):
    if "|" in linha:
        nome, url = (p.strip() for p in linha.split("|", 1))
        if not url.startswith("https://"):
            sys.exit(f"Link precisa começar com https://  ->  {linha.strip()}")
        itens.append({"nome": nome, "url": url})
if not itens:
    sys.exit("Nenhum link encontrado na lista")

senha = os.environ.get("DOWNLOADS_SENHA")
if not senha:
    senha = getpass.getpass("Senha da área de downloads: ")
    if senha != getpass.getpass("Repita a senha: "):
        sys.exit("As senhas não conferem")

sal, iv = os.urandom(16), os.urandom(12)
chave = PBKDF2HMAC(algorithm=hashes.SHA256(), length=32, salt=sal, iterations=ITERACOES).derive(senha.encode("utf-8"))
cifrado = AESGCM(chave).encrypt(iv, json.dumps(itens, ensure_ascii=False).encode("utf-8"), None)
b64 = lambda b: base64.b64encode(b).decode()
bloco = json.dumps({"s": b64(sal), "i": b64(iv), "c": b64(cifrado)}, separators=(",", ":"))

pagina = open(PAGINA, encoding="utf-8").read()
nova, n = re.subn(r"/\*downloads:inicio\*/.*?/\*downloads:fim\*/",
                  lambda m: "/*downloads:inicio*/" + bloco + "/*downloads:fim*/", pagina, flags=re.S)
if n != 1:
    sys.exit("Não achei os marcadores /*downloads:inicio*/ e /*downloads:fim*/ em pagina.html")
open(PAGINA, "w", encoding="utf-8", newline="\n").write(nova)
print(f"{len(itens)} links cifrados em fonte/pagina.html. Agora rode: python fonte/montar.py")
