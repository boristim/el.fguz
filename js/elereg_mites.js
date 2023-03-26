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
        fileField.setAttribute('accept', '.xlsx')
        fileField.addEventListener('change', () => {
          if (fileField.files.length > 0) {
            let throbber = Object.create(Throbber)
            throbber.start()
            let data = new FormData()
            data.append('files', fileField.files[0], fileField.files[0].name)
            fetch('/admin/mites/xls', {
              method: 'POST',
              body: data
            }).then(response => response.json())
              .then(response => {
                console.log(response)
                throbber.stop()
              })
              .catch((reason) => {
                console.log(reason)
                throbber.stop()
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
