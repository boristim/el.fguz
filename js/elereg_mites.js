/*jshint esversion: 11 */
((d) => {
  let exportLink = d.querySelector('.mites-export')
  if (exportLink) {
    exportLink.href = d.location.toString().replace('/admin/mites', '/admin/mites/xls')
  }
  let importLink = d.querySelector('.mites-import')
  if (importLink) {
    importLink.addEventListener('click', () => {
      let fileField = d.querySelector('input[name=mitesXls]')
      if (null === fileField) {
        fileField = d.createElement('INPUT')
        fileField.setAttribute('name', 'mitesXls')
        fileField.setAttribute('type', 'file')
        fileField.setAttribute('accept', '.xlsx,.ods')
        fileField.addEventListener('change', () => {
          if (fileField.files.length > 0) {
            let data = new FormData()
            data.append('files', fileField.files[0], fileField.files[0].name)
            let throbber = Object.create(Throbber)
            throbber.start()
            let popup = Object(DisplayMessage)
            fetch('/admin/mites/xls', {
              method: 'POST',
              body: data
            }).then(response => response.json())
              .then(response => {
                popup.show(response['log'])
                throbber.stop()
              })
              .catch((reason) => {
                throbber.stop()
                popup.show(reason.toString())
              })
          }
        })
      }
      fileField.click()
    })
  }
  let header = d.querySelector('.view-mites .view-header')
  if (header) {
    let regNoHeader = d.querySelector('th.views-field-field-mite-reg-no')
    if (regNoHeader) {
      let chk = d.createElement('INPUT')
      chk.setAttribute('type', 'checkbox')
      chk.addEventListener('click', (event) => {
        let ch = event.currentTarget.checked
        d.querySelectorAll('.chk-no').forEach((item) => {
          item.checked = ch
          if (ch) {
            item.setAttribute('checked', 'checked')
          }
          else {
            item.removeAttribute('checked')
          }
        })
        return true
      })
      regNoHeader.appendChild(chk)
      let regNo = d.querySelectorAll('td.views-field-field-mite-reg-no')
      if (regNo) {
        regNo.forEach((item) => {
          let chk = d.createElement('INPUT')
          chk.setAttribute('type', 'checkbox')
          chk.classList.add('chk-no')
          chk.setAttribute('name', 's[' + item.innerText + ']')
          item.appendChild(chk)
        })
        let btn = d.createElement('A')
        btn.classList.add('button--primary')
        btn.classList.add('form-submit')
        btn.classList.add('button')
        btn.href = '#'
        btn.innerText = 'Отправить SMS выбранным'
        btn.addEventListener('click', () => {
          let chkS = d.querySelectorAll('.chk-no:checked')
          if (!chkS.length) {
            return false
          }
          let data = new FormData()
          chkS.forEach((item) => {
            data.append(item.getAttribute('name'), item.getAttribute('name').replace('s[', '').replace(']', ''))
          })
          let throbber = Object.create(Throbber)
          throbber.start()
          fetch('/admin/mites/sms', {
            method: 'POST',
            body: data
          })
            .then(response => {
              // console.log(response.status)
              if (response.status !== 200) {
                return {'log': 'Some error'}
              }
              return response.json()
            })
            .then(resp => {
              throbber.stop()
              if (resp.hasOwnProperty('sent')) {
                resp.sent.forEach((item) => {
                  let inp = d.querySelector(`input[name="${item}"]`)
                  // console.log(inp)
                  if (inp) {
                    inp.removeAttribute('checked')
                    inp.checked = false
                  }
                })
              }
            })
            .catch((reason) => {
              throbber.stop()
              console.warn(reason)
            })
          return false;
        })
        header.appendChild(btn)
      }
    }
  }
})(document)

const Throbber = {
  throbber: null,
  started: false,
  start: function () {
    this.started = true
    if (null === this.throbber) {
      let self = this
      setTimeout(() => {
        if (self.started) {
          self.throbber = document.createElement('DIV')
          self.throbber.classList.add('elereg-throbber')
          document.querySelector('body').appendChild(self.throbber)
        }
      }, 973)
    }
  },
  stop: function () {
    if (null !== this.throbber) {
      this.throbber.remove()
    }
    this.started = false;
  }
}

const DisplayMessage = {
  msg: null,
  msgText: null,
  closeBtn: null,
  show: function (log) {
    if (null === this.msg) {
      this.msg = document.createElement('DIV')
      this.msg.classList.add('elereg-mite-popup')
      this.msgText = document.createElement('DIV')
      this.msgText.classList.add('elereg-mite-popup-inner')
      this.closeBtn = document.createElement('SPAN')
      this.closeBtn.classList.add('elereg-mite-popup-close')
      this.closeBtn.innerText = 'X'
      let self = this
      this.msg.addEventListener('click', () => {
        self.hide()
      })
      this.closeBtn.addEventListener('click', () => {
        self.hide()
      })
      document.querySelector('body').appendChild(this.msg)
      document.querySelector('body').appendChild(this.msgText)
      document.querySelector('body').appendChild(this.closeBtn)

    }
    let text = '<ul>'
    log.forEach((item) => {
      text += `<li><span>${item.dt}</span>: ${item.msg}</li>`
    })
    text += '</ul>'
    this.msgText.innerHTML = text
  },
  hide: function () {
    if (null !== this.msg) {
      this.msg.remove()
      this.msgText.remove()
      this.closeBtn.remove()
      this.msg = null
    }
  }
}
