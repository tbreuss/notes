
import {http} from '@/utils/http'
import storage from '../utils/storage'

export default {
  login (email, pass, cb) {
    cb = arguments[arguments.length - 1]
    if (storage.getApiToken() !== null) {
      if (cb) cb(true)
      this.onChange(true)
      return
    }
    postLogin({username:email, password:pass})
      .then(token => {
        storage.setApiToken(token)
        if (cb) cb(true)
        this.onChange(true)
      })
      .catch(error => {
        if (cb) cb(false, error.response.data)
        this.onChange(false)
      })
  },

  getToken () {
    return storage.getApiToken()
  },

  getPayload () {
    let token = this.getToken()
    let base64Url = token.split('.')[1]
    let base64 = base64Url.replace('-', '+').replace('_', '/')
    return JSON.parse(window.atob(base64))
  },

  hasPermission (scope)
  {
    let payload = this.getPayload()
    // todo: check if user.role exists
    if (payload.user.role == 'admin') {
      return true
    }
    // todo: check if user.scopes exists and is an array
    if (payload.user.scopes.indexOf(scope) != -1) {
      return true
    }
    return false
  },

  hasPermissionForUser(scope, userId)
  {
    let payload = this.getPayload()
    // todo: check if user.role exists
    if (payload.user.role == 'admin') {
      return true
    }
    // todo: check if user.scopes exists and is an array
    if (payload.user.scopes.indexOf(scope) != -1) {
      if (payload.user.id == userId) {
        return true
      }
    }
    return false
  },

  logout (cb) {
    storage.setApiToken(null)
    if (cb) cb()
    this.onChange(false)
  },

  loggedIn () {
    return storage.getApiToken() !== null
  },

  onChange () {}
}

function postLogin (params) {
  var url = 'auth/login'
  var config = {
    headers: {
      'Content-type': 'application/x-www-form-urlencoded'
    }
  }
  return http
    .post(url, params, config)
    .then(response => response.data)
}
