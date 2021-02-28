Hello,

This API allows you to retrieve from Mojang's servers the head of a Minecraft Java Edition account to display it on your website. The advantage of this API is that it relies on the use of a REDIS cache to speed up requests and avoid making constant requests to the Mojang servers.

In order to do this you need to clone this directory into your web directory and have a REDIS server at your disposal.

When everything is in place, on your server, edit the file head.php and replace the REDIS login information to match your configuration, if you have not put my password on your REDIS instance delete the password line.

Then all should be fine. Test the API by accessing it from your website.


https://exemple.com/head.php?u=username&s=150

u= The Minecraft nickname, works with the Minecraft Bedrock Edition nickname with a * in front (this displays steve's skin).
s= The size of your rendering per pixel

I hope you like this API, feel free to improve it.

Translated with www.DeepL.com/Translator (free version)