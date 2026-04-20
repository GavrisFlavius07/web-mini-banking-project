# MINIBANKING API

## Exposed api

### Movimenti

1. GET `/account/{id_account}/transaction` per ottenere l'elenco dei movimenti
2. GET `/account/{id_account}/transaction/{id}` per ottenere il dettaglio di un movimento
3. POST `/account/{id_account}/deposit` per registrare un deposito
4. POST `/account/{id_account}/withdrawal` per registrare un prelievo
5. PUT `/account/{id_account}/transaction/{id}` per modificare la descrizione di un movimento
6. DELETE `/account/{id_account}/transaction/{id}` per eliminare un movimento secondo la regola scelta

### Saldo

7. GET `/account/{id_account}/balance` per ottenere il saldo attuale

### Conversione del saldo

8. GET `/account/{id_account}/balance/convert/fiat?to=USD` per convertire il saldo in una valuta fiat
9. GET `/account/{id_account}/balance/convert/crypto?to=BTC` per convertire il saldo in una criptovaluta

## Su Linux
`MY_UID=$(id -u) MY_GID=$(id -g) docker-compose up`

## Su Windows
`docker-compose up`
