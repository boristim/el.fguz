
**Электронная регистратура**

--------------------------------------------
###### Requirements:
* Drupal 10
* php 8.1
* RabbitMQ
* Python3
* Supervisor
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
```ini
[program:erl]
command=/usr/bin/php8.1 vendor/bin/drush erl
autostart=true
autorestart=true
directory={SITE PATH}
stderr_logfile=/var/log/erl.err.log
stdout_logfile=/var/log/erl.out.log
```

/etc/supervisor/conf.d/tg.conf:
```ini
[program:tg]
command=/usr/bin/python3 tg.py
autostart=true
autorestart=true
directory= {SITE PATH}/tg
stderr_logfile=/var/log/erl_tg.err.log
stdout_logfile=/var/log/erl_tg.out.log
```

