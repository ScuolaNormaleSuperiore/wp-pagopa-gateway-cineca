A .pem file is required.
If you have a .p12 file you can use this command to convert it:

openssl pkcs12 -in cert.p12 -out cert.pem -clcerts
