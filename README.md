# Tragopan
Tragopan is a tool create self-signed certificates for using with SSL/TLS, based on your own CA.
All issued certificates are valid for the given hostname and the next level of sub-domains.

For example, if you provide the hostname `my-awesome.vm` the certificate as also valid for all 3rd level domains 
(eg. `www.my-awesome.vm`, `search.my-awesome.vm`). 

## Requirements
The following programs are required to run Tragopan:
- at least `PHP 5.5.9` (`PHP 7.0` or higher is recommended)
- openssl
- [composer](http://getcomposer.org)

## Installation
To install Tragopan follow the steps below in the given order.

1. Download and extract the release package.
2. Run `composer install` in the root directory.
3. Provide the requested parameters. Take a look into the [Parameters](#parameters) section for further information.
4. Run the command `bin/tragopan ca:create` to create your CA key and certificate.
5. Start creating new certificates for your own usage (eg. for development virtual machines).

## <a name="parameters"></a>Parameters described
```text
ca.country            ... Your country code (two-letters, eg. DE)
ca.state              ... Your state or province (full name, eg. Germany)
ca.locality           ... The name of your city (eg. Berlin)
ca.organization       ... The name of your company (eg. Awesome Company Ltd.)
ca.organizationalUnit ... Title of your department (eg. Task Force Epic-Development)
ca.name               ... The name for of your root certificate (eg. Awesome Company CA)
ca.email              ... E-Mail address for contact purposes
ca.secret             ... The password used to secure the private CA key
ca.api-key            ... This key is used to download the CA certificate
```

## Usage
After setting up the application, you can start creating certificates which will be signed with your CA.

To create a new certificate just send a HTTP-POST request to `http://yourdoamin-or-ip/cert` and
provide the certificate information in the POST body.
There are two required fields `host` and `cert[name]`.
Optional fields are
- `cert[country]`
- `cert[state]`
- `cert[locality]`
- `cert[organization]`
- `cert[organizationalUnit]`

The meaning of these fields are the same as above and will be visible in your certificate.

A minimal request will look like
```bash
$ curl http://yourdoamin-or-ip/cert -d 'host=my-awesome.vm&cert[name]=My+Awesome+VM'
```

A full one is like
```bash
$ curl http://yourdoamin-or-ip/cert -d 'cert[country]=DE&cert[state]=Germany&cert[locality]=Berlin&cert[organization]=Awesome+Company+CA&cert[organizationalUnit]=Development+Special+Forces&cert[name]=My+Awesome+VM&host=my-awesome.vm'
```

The second way to provide the information is to use JSON instead of URL encoded POST data.
```bash
$ curl http://yourdoamin-or-ip/cert -H 'Content-Type: application/json; charset=utf-8' -d '{
    "cert":{
        "country":"DE",
        "state":"Germany",
        "locality":"Berlin",
        "organization":"Awesome Company CA",
        "organizationalUnit":"Development Special Forces",
        "name":"My Awesome VM"
    },
    "host":"my-awesome.vm"
}'
```

All of these requests will send a JSON response containing two URIs to download the key and the certificate.
```json
{
    "key":"/cert/12345abcde",
    "cert":"/cert/67890fghij"
}
```
**Important**: These download links are valid for one hour and can only be used once!

## Download the CA certificate
To get the generated certificates working as expected you need to download the CA certificate.
This can be done by sending a HTTP-GET request to `http://yourdoamin-or-ip/cert` and providing the `ca.api-key` as
the HTTP header `X-CA-Api-Key`. After you have downloded the CA certificate you must install it as
a Root Certificate Authority and fully trust it.
```bash
$ curl http://yourdoamin-or-ip/cert -H 'X-CA-Api-Key: IAmNotSecure'
```

