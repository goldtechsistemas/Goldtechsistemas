# Como editar o site

O site publicado é o `index.html` da raiz do repositório. Ele é montado a partir de `fonte/pagina.html`. Não edite o `index.html` direto, porque a próxima montagem apaga a mudança.

## Mudar textos, seções ou cores

1. Edite `fonte/pagina.html`. Os trechos entre chaves duplas, como `{{logo}}` e `{{wa}}`, são preenchidos na montagem.
2. Rode `python fonte/montar.py`. Ele gera o `index.html` da raiz e avisa se alguma tag ficou aberta ou se falta imagem.
3. Abra o `index.html` no navegador para conferir.
4. Faça commit e push. A Vercel publica sozinha em goldtechsistemas.vercel.app.
5. Na Hostinger (goldtechsistemas.com), envie o `index.html` novo, e as imagens novas se houver, para a pasta `public_html` pelo Gerenciador de Arquivos.

## Imagens

- Todas ficam em `img/`, em WebP.
- Para adicionar ou trocar uma tela do sistema:
  `python fonte/imagens.py print.png p_nome --desfocar 100,200,400,260`
  Depois use `{{p_nome}}` no `src` da imagem em `fonte/pagina.html`. Precisa do Pillow (`pip install pillow`).
- O repositório é público. Desfoque sempre nomes de clientes e fornecedores, chaves de nota, valores e contatos antes de subir um print.
- Ao trocar uma imagem, prefira um nome novo. Com o mesmo nome, quem já visitou o site pode ver a antiga por até 7 dias, por causa da regra de cache do `.htaccess`.

## Onde fica cada coisa

- WhatsApp, segmentos e logos de clientes: no começo de `fonte/montar.py` (constante `WA` e lista `SEGMENTOS`).
- Formulário: envia pelo Formspree (`formspree.io/f/maqkenaz`) e registra o evento Lead no Pixel da Meta.
- Regra de cache da Hostinger: `.htaccess`.
- A pasta `fonte/` não vai para a Vercel (está no `.vercelignore`) e não precisa ir para a Hostinger.
