**Электронная регистратура**

--------------------------------------------
* Настройка календаря /admin/config/system/elereg
* Настройка SMPP шлюза /admin/config/system/elereg/sms
* Список и настройка специальных дней /admin/config/system/elereg/spec
* Полный календарь регистратуры /admin/config/system/elereg/calendar-full
* Текущий записи /admin/config/system/elereg/reg
* Запланированные выездные мероприятия /admin/config/system/elereg/departures
* Настройка RabbitMQ /web/sites/default/settings.php
--------------------------------------------
Страница для фрейма - /elereg

/etc/supervisor/conf.d/erl.conf:
```apacheconf
[program:erl]
command=<SITE_PATH>/erl.sh
autostart=true
autorestart=true
stderr_logfile=/var/log/erl.err.log
stdout_logfile=/var/log/erl.out.log
```

<SITE_PATH>/erl.sh:
```shell
#!/bin/env sh
cd <SITE_PATH> && /usr/bin/php8.1 vendor/bin/drush erl
```
