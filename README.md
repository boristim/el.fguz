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

Конфиги

/etc/supervisor/conf.d/erl.conf:

`
[program:erl]
command=/var/www/elereg/erl.sh
autostart=true
autorestart=true
stderr_logfile=/var/log/erl.err.log
stdout_logfile=/var/log/erl.out.log
user=root
`

/var/www/elereg/erl.sh:
`
#!/bin/env sh
export HOME=/root
cd /var/www/elereg && /usr/bin/php8.1 vendor/bin/drush erl
`
