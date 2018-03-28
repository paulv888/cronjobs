cronjobs
========

This is a various collection of PHP files to process data / event (interface) into or from my Home Automation web front-end

Currently interfacing to:

       Brand           Model            Type                    Interface                    
       ================================================================================
       Filtrete        3M CT50          Wifi Thermostat         Get/JSON
       Arduino         X.X              Auto Coop Door          Post/Html
       Insteon         2242-222 INS     Insteon Hub             Get/Html
       Insteon         2242-222 INS     Insteon Hub             TCP Stream (Events)
       Sony            RZ25n            PTZ Camera              Get/Html (Mjpeg)
       D-Link          DCS-932L         IP Camera               MJPeg
       EDIMax          3115W            IP Camera               MJPeg
       Axis            P5532            PTZ Camera              Get/Html (MJPeg)
       Keebox          IPC1000WI        IP Camera               Post/Html (MJPeg)
       Foscam          Clone            PTZ Camera              Get/Html (MJPeg)
       Wansview        W2               IP Camera               Get/JSON (MJPeg)
       Irrigation
       Caddy           IC-W1 V2         Irrigation              Post/Html
       Wink            HUB V1           Wink Hub                JSON/JSON
       iTach           IP2IR            Infrared Hub            TCP
       Kodi            16.1             Media Center            JSON/JSON
       Yamaha          RX-V777BT        AV Receiver             Post/Html
       HA-Bridge       V2.0.5           Alexa HA Brige          Put/Html (https://github.com/armzilla/amazon-echo-ha-bridge)
       TP-Link         HS100            Smart Plug              TCP64 (https://georgovassilis.blogspot.com/2016/05/controlling-tp-link-hs100-wi-fi-smart.html)
       ESP Easy        V1.19            Thermo                  Get/Post
       ESP Easy/Ard    V1.19            Smoker-Control          Get/Post
       PushBullet      https://github.com/ivkos/Pushbullet-for-PHP
       Asus            RT-65N           Router                  Get/Html (Telnet)
       Undergroud Wth  API              Weather Info            Get/JSON
       Yahoo           API              Dusk/Dawn Info          Get/JSON
       Yahoo           Streamer-API     Stock-Ticks             Get/JSON
       IB              API              Stock ORders            Get/JSON
       Amazon          FireTV Stick     Media Player            ADB`
       Amazon          Alexa            Voice Service           Post/JSON
