<template>
    <el-container>
        <el-main>
            <div v-if="isAuthenticated">
                <h1>Angemeldet</h1>
                <el-button type="button" @click="logout">Logout</el-button>
            </div>
            <div v-else>
                <h1>Login</h1>
                <el-form label-width="120px">
                    <el-form-item label="Benutzername">
                        <el-input v-model="form.username" @keyup.enter.native="login"></el-input>
                    </el-form-item>
                    <el-form-item label="Password">
                        <el-input type="password" v-model="form.password" @keyup.enter.native="login"></el-input>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="login" :disabled="disabled">Login</el-button>
                    </el-form-item>
                </el-form>
                {{ errors }}
            </div>
        </el-main>
    </el-container>
</template>

<script>
  import { postLogin } from '@/utils/api'
  import auth from '@/utils/auth'

  export default {
    name: 'LoginPage',
    data () {
      return {
        form: {
          username: '',
          password: ''
        },
        formSent: false,
        errors: {},
        isAuthenticated: auth.loggedIn(),
        disabled: false
      }
    },
    methods: {
      login () {
        this.disabled = true
        auth.login(this.form.username, this.form.password, (loggedIn, errors) => {
          this.disabled = false
          if (!loggedIn) {
            this.errors = errors
          } else {
            this.$message({
              message: 'Du bist angemeldet',
              type: 'success'
            })
            this.$router.replace(this.$route.query.redirect || '/')
          }
          this.formSent = true
        })
      },
      reset() {
        this.formSent = false
      },
      getClass (field) {
        if (!this.formSent) {
          return 'form-control'
        }
        if (field in this.errors) {
          return 'form-control is-invalid'
        }
        return 'form-control is-valid'
      }
    },
    created () {
    }
  }
</script>

<style scoped>

</style>
