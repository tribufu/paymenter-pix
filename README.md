# Pix for Paymenter

[Pix](https://www.bcb.gov.br/estabilidadefinanceira/pix) payments for [Paymenter](https://paymenter.org) invoices in
Brazilian reais (BRL), with a QR code and the Pix copy and paste code. It works in two modes:

- **Pix API of the bank**: each payment is an immediate charge (`cob`) of the Pix API standard of the Central Bank of
  Brazil, offered by banks such as Banco Inter, Sicoob, Itaú, and Santander. The invoice is paid automatically when the
  webhook of the bank notifies the payment, or when the payer presses "I have paid". Either way the charge is read from
  the API, so a forged notification pays nothing.
- **Manual**: without the API, it shows the static BR Code of your Pix key, with the amount and number of the invoice.
  Payments are confirmed by hand on the **Pix** page of `/admin`, which lists the pending invoices in BRL.

The gateway is only offered for invoices in BRL. Each Pix is recorded once, by its end to end id.

Tested with Paymenter v1.5.9.

## Installation

Copy the files of this repository to `extensions/Gateways/Pix` of Paymenter; the directory must have this name:

```bash
git clone https://github.com/tribufu/paymenter-pix.git extensions/Gateways/Pix
```

Add a **Pix** gateway in `/admin`, under Gateways, and fill in its settings. Add the BRL currency to Paymenter if it
does not have it.

| Setting       | Description                                                                                      |
| ------------- | ------------------------------------------------------------------------------------------------ |
| Pix Key       | CPF or CNPJ with digits only, email, phone with the country code (`+5511999999999`), or random key. |
| Receiver Name | Name of the account that receives the payments, up to 25 characters.                            |
| Receiver City | City of the account that receives the payments, up to 15 characters.                            |

Leave the API settings empty for the manual mode.

## Pix API

Create an API integration in the bank, with the scopes `cob.read`, `cob.write`, `pix.read`, `webhook.read`, and
`webhook.write`, and download its certificate. For Banco Inter:

| Setting       | Value                                                                    |
| ------------- | ------------------------------------------------------------------------ |
| Pix API URL   | `https://cdpj.partners.bancointer.com.br/pix/v2`                         |
| Token URL     | `https://cdpj.partners.bancointer.com.br/oauth/v2/token`                 |
| Client ID     | Client ID of the integration.                                            |
| Client Secret | Client secret of the integration.                                        |
| Certificate   | The `.crt` of the integration, in PEM.                                   |
| Private Key   | The `.key` of the integration, in PEM.                                   |

The secret, certificate, and private key are stored encrypted, and the certificate is written to a temporary file only
during each request to the bank.

Saving the gateway registers the webhook in the bank, at `https://<paymenter>/extensions/pix/webhook`, which must be
reachable from the internet over HTTPS. The webhook is rate limited per address.

## License

[MIT](LICENSE.txt)
