/*jshint esversion: 8 */
/* global console */
/* global document */
((settings) => {
  const main = {
    data: {},
    operations: ['renderServices', 'renderWeeks', 'renderHours', 'renderFIO'],
    curOperation: 0,
    selects: '',
    store: {},
    storeTitles: {},
    init: async function () {
      let self = this
      await fetch(settings['elereg']['endPoint'], {method: 'GET'}).then(response => response.json()).then(data => {
        self.data = data
        document.querySelector(settings['elereg']['rootElement']).innerHTML = '<div class="elereg-main"><span id="elereg-selected-values"></span><div id="elereg-form"></div><button id="elereg-form-next">Далее</button></div>'
        self.root = document.getElementById('elereg-form')
        document.getElementById('elereg-form-next').addEventListener('click', () => {
          app.nextOperation()
        })
      })
    },
    submitValues: function () {
      let fio = this.root.querySelector('input[name=fio]').value
      let tel = this.root.querySelector('input[name=tel]').value
      let conf = this.root.querySelector('input[type=checkbox]')
      let msg = []
      if (fio.length <= 1) {
        msg.push('Укажите фамилию')
      }
      this.storeTitles['fio'] = fio
      this.storeTitles['tel'] = tel
      localStorage.setItem('eleregFIO', fio)
      localStorage.setItem('eleregTel', tel)
      tel = tel.replace(/\D/g, '')
      if ((tel.length < 10) || (tel.length > 11)) {
        msg.push('Укажите корректный номер телефона')
      }
      if (!conf.checked) {
        msg.push('Согласитесь на обработку персональных данных')
      }
      this.store['tel'] = tel
      this.store['fio'] = fio
      if (!msg.length) {
        fetch(settings['elereg']['endPoint'], {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify(this.store)
        }).catch(reason => {
          console.log(reason)
        })
          .then(response => response.json())
          .then(data => {
            switch (data['status']) {
              case 'error':
                this.alert('Ошибка создания талона')
                console.log(data)
                break
              case 'warning':
                this.alert(data['message'])
                window.location = '?' + Math.random()
                break
              case 'ok':
              default:
                document.getElementById('elereg-selected-values').innerHTML = ''
                if (data.hasOwnProperty('qr')) {
                  this.qr = data.qr
                }
                this.printTicket()
                break

            }
          })
      }
      else {
        this.alert(msg.join('\n'))
      }
    },
    printDiv: function (divId) {
      let printDiv = document.getElementById(divId).innerHTML
      let original = document.body.innerHTML
      document.body.innerHTML = printDiv
      window.print()
      document.body.innerHTML = original
      document.getElementById('elereg-print-page').addEventListener('click', () => {
        app.printDiv('elereg-ticket-print')
      })
    },
    printTicket: function () {
      let qr = ''
      if (this.hasOwnProperty('qr')) {
        qr = '<img src="' + this.qr + '" alt="QR"/>';
      }
      this.root.innerHTML = `<div id="elereg-ticket-print">
            <i>Талон на получение услуги:</i> ${this.storeTitles['Services']}<br>
            на <strong>${this.storeTitles['Hours']}</strong>ч. <strong>${this.storeTitles['Weeks']}</strong><br>
            ФИО ${this.store['fio']}<br>
            Телефон ${this.store['tel']}<br>
            ${qr}
            </div>
            <button id="elereg-print-page">Распечатать страницу</button>                `
      document.getElementById('elereg-print-page').addEventListener('click', () => {
        app.printDiv('elereg-ticket-print')
      })
    },
    nextOperation: function () {
      let values = []
      let titles = []
      this.root.querySelectorAll('input[type=radio]:checked,input[type=checkbox]:checked,input[type=text],input[type=tel]').forEach((elem) => {
        if (elem.value.length > 0) {
          values.push(elem.value)
          let ttl = elem.parentElement.innerText
          if (ttl.length) {
            titles.push(elem.parentElement.innerText)
          }
          else {
            titles.push(document.querySelector(`label[for="${elem.id}"]`).innerText)
          }
        }
      })
      if (Object.keys(values).length) {
        this.storeTitles[this.operations[this.curOperation].replace('render', '')] = titles.join('<br>')
        this.selects += titles.join(', ') + '<br>'
        document.getElementById('elereg-selected-values').innerHTML = this.selects
        this.store[this.operations[this.curOperation].replace('render', '')] = values
        this.curOperation++
        this[this.operations[this.curOperation]]()
      }
      else {
        this.alert('Введите данные')
      }
    },
    alert: (msg) => {
      alert(msg)
    },
    setRadioListener: function () {
      this.root.querySelectorAll('input[type=radio]').forEach((elem) => {
        elem.parentElement.classList.add('elereg-radio-hidden')
        elem.addEventListener('click', () => {
          app.nextOperation()
        })
      })
      document.getElementById('elereg-form-next').classList.add('hidden')
    },
    templateService: (service) => {
      return `<tr><td class="checkbox"><input type="checkbox" name="services[]" id="sid-${service.id}" value="${service.id}"></td><td><label for="sid-${service.id}">${service.name}</label></tr>`
    },
    templateHour: (hour) => {
      let input = ''
      let cls = 'off'
      if (hour.s) {
        input = `<input type="radio" value="${hour.t}" name="hour">`
        cls = 'on'
      }
      return `<li><label class="elereg-status-${cls} elereg-radio">${input}${hour.t}</label></li>`
    },
    templateDay: (day) => {
      let input = ''
      let cls = 'off'
      if (day.s) {
        input = `<input type="radio" value="${day.d}" name="day">`
        cls = 'on'
      }
      return `<li><label class="elereg-status-${cls} elereg-radio">${input}<small>${day.w}</small><br>${day.d}</label></li>`
    },
    templateWeek: function (week) {
      let html = '<ul class="week">'
      week.forEach((day) => {
        html += this.templateDay(day)
      })
      html += '</ul>'
      return html
    },
    renderFIO: function () {
      let fioVal = localStorage.getItem('eleregFIO')
      fioVal = fioVal ? `value='${fioVal}'` : ''
      let telVal = localStorage.getItem('eleregTel')
      telVal = telVal ? `value='${telVal}'` : ''

      this.root.innerHTML =
        `<div class="elereg-element-fio"><input ${fioVal} type="text" name="fio" placeholder="Введите вашу фамилию и имя" required><br>
<input type="text" name="tel" maxlength="11" ${telVal} placeholder="Укажите номер телефона" required><br>
<label><input type="checkbox" name="confidence">Я принимаю ответственность за правильность предоставленных персональных данных и <a href="/confidence" target="_blank">даю согласие</a> на их обработку.</label><br>
<button id="elereg-submit">Забронировать время</button>
</div>`;
      document.getElementById('elereg-submit').addEventListener('click', () => {
        app.submitValues()
      })
    },
    renderHours: function () {
      let day = this.store['Weeks'][0]
      let dow = {}
      let BreakException = {}
      try {
        this.data['dates'].forEach((week) => {
          return week.forEach((_dow) => {
            if (_dow.d === day) {
              dow = _dow
              throw BreakException
            }
          })
        })
      }
      catch (e) {
        if (e !== BreakException) {
          throw e
        }
      }
      let html = '<ul class="hours">'
      dow['h'].forEach((hour) => {
        html += this.templateHour(hour)
      })
      html += '</ul>'
      this.root.innerHTML = html
      this.setRadioListener()
    },
    renderWeeks: function () {
      let html = '<ul class="weeks">'
      this.data['dates'].forEach((week) => {
        html += '<li>' + this.templateWeek(week) + '</li>'
      })
      html += '</ul>'
      this.root.innerHTML = html
      this.setRadioListener()
    },
    renderServices: function () {
      let html = '<table>'
      html += '<tr><th>Выбрать услугу(и)<br><i>(поставить галочку)</i></th><th>Наименование услуги</th></tr>'
      this.data['services'].forEach((data) => {
        html += this.templateService(data)
      })
      html += '</table>'
      this.root.innerHTML = html
    },

    render: function () {
      this.init().then(() => {
        this[this.operations[this.curOperation]]()
      })
    }
  }
  if (settings.hasOwnProperty('elereg')) {
    window.app = Object.create(main)
    window.app.render()
  }
})(drupalSettings)
