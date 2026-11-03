# Integração com o Controle do Sintegra

Outro sistema pode cadastrar clientes e informar a data em que o Sintegra foi gerado.

## Endereço

```
POST https://goldtechsistemas.com/controle/?api=externo
Content-Type: application/json
X-Chave: <chave>
```

A chave é gerada no controle, no botão **Integração**. Ela aparece uma vez só. Gerar uma chave nova faz a antiga parar de funcionar.

Também funciona mandar a chave como `Authorization: Bearer <chave>`. Se o servidor do outro sistema remover esse cabeçalho, use `X-Chave`.

## Campos

| Campo | Obrigatório | O que é |
|---|---|---|
| `cnpj` | sim | Com ou sem pontuação. Identifica o cliente. |
| `nome` | só para cliente novo | Nome da empresa. Se vier para um cliente que já existe, atualiza o nome. |
| `gerado_em` | não | Data em que o Sintegra foi gerado, `AAAA-MM-DD`. |
| `mes` | não | Mês de entrega a que essa data se refere, `AAAA-MM`. |
| `competencia` | não | Use no lugar de `mes` se preferir a competência, `AAAA-MM`. A entrega é o mês seguinte. |
| `chamamos` | não | Como chamamos o cliente. |
| `prazo_dia` | não | Dia do prazo para gerar, de 1 a 28 (padrão 10). |
| `automatico` | não | `true` ou `false`: envio automático. |
| `dia_envio` | com `automatico: true` | Dia do envio automático, de 1 a 28. |
| `enviar_arquivos` | não | `true` ou `false`: o EnviarArquivos está instalado no cliente. |
| `master_remote` | não | `true` ou `false`: o Master Remote está instalado no cliente. |

Sem `mes` nem `competencia`, a data vai para o mês do próprio `gerado_em`.

Quando o CNPJ já existe, o cliente é atualizado. Nunca é criado um segundo cliente com o mesmo CNPJ. Campos que não forem enviados ficam como estão.

## Um cliente

```bash
curl -X POST "https://goldtechsistemas.com/controle/?api=externo" \
  -H "Content-Type: application/json" \
  -H "X-Chave: SUA_CHAVE" \
  -d '{"cnpj": "11.222.333/0001-81", "nome": "Padaria São João", "gerado_em": "2026-11-05"}'
```

Resposta (HTTP 200):

```json
{"ok": true, "cnpj": "11222333000181", "acao": "criado", "mes": "2026-11"}
```

Com erro (HTTP 400):

```json
{"ok": false, "cnpj": "11222333000180", "erro": "CNPJ inválido."}
```

## Vários clientes de uma vez

Mande uma lista (até 500 por chamada), ou `{"clientes": [ ... ]}`:

```json
[
  {"cnpj": "11.222.333/0001-81", "nome": "Padaria São João", "gerado_em": "2026-11-05"},
  {"cnpj": "11.444.777/0001-61", "nome": "Mercado Bom", "competencia": "2026-10", "gerado_em": "2026-11-08"}
]
```

Resposta (HTTP 200), com o resultado de cada um na mesma ordem. Os que tiverem erro não impedem os outros de serem gravados:

```json
{"gravados": 2, "com_erro": 0, "resultados": [{"ok": true, "cnpj": "11222333000181", "acao": "atualizado", "mes": "2026-11"}, {"ok": true, "cnpj": "11444777000161", "acao": "criado", "mes": "2026-11"}]}
```

## Códigos de resposta

| HTTP | Quando |
|---|---|
| 200 | Gravado (para lista, veja `resultados`). |
| 400 | Dados inválidos. A mensagem diz o que corrigir. |
| 401 | Chave errada ou integração desligada. |
| 405 | Não foi POST. |
| 500 | O servidor não conseguiu gravar. Tente de novo. |

A tela do controle confere a cada minuto se algo mudou e mostra o que o outro sistema gravou.
