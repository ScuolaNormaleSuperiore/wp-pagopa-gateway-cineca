A .pem file is required.

If Cineca provides a .p12/.pk12 file, it must be converted to .pem before it
can be used by this plugin.

With OpenSSL 3, older .p12 files may fail to convert because legacy
algorithms such as RC2 are disabled by default. In that case use:

openssl pkcs12 -legacy -in cert.p12 -out cert.pem -clcerts

This command:
- reads the password of the original .p12 file
- creates a .pem file
- asks for a passphrase for the output .pem file

If you want to keep using a protected certificate in the plugin, do NOT use
-nodes. You can set the same password as the original .p12, or a new one.
The plugin will use the passphrase of the .pem file in the "Certificate
password" setting.

If you really need an unencrypted .pem file, you can use:

openssl pkcs12 -legacy -in cert.p12 -out cert.pem -clcerts -nodes

In that case the private key is not protected by passphrase, so the
"Certificate password" field in the plugin should be left empty.
