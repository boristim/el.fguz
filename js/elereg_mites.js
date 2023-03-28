/*jshint esversion: 11 */
(() => {
  let exportLink = document.querySelector('.mites-export')
  if (exportLink) {
    exportLink.href = document.location.toString().replace('/admin/mites', '/admin/mites/xls')
  }
  let importLink = document.querySelector('.mites-import')
  if (importLink) {
    importLink.addEventListener('click', () => {
      let fileField = document.querySelector('input[name=mitesXls]')

      if (null === fileField) {
        fileField = document.createElement('INPUT')
        fileField.setAttribute('name', 'mitesXls')
        fileField.setAttribute('type', 'file')
        fileField.setAttribute('accept', '.xlsx,.ods')
        fileField.addEventListener('change', () => {
          if (fileField.files.length > 0) {
            let throbber = Object.create(Throbber)
            throbber.start()
            let data = new FormData()
            data.append('files', fileField.files[0], fileField.files[0].name)
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
})(drupalSettings)

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
