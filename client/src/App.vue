<template>
    <el-container>
        <el-header height="165px">
            <el-menu :default-active="'/articles'" :router="true" class="el-menu" mode="horizontal">
                <el-menu-item index="/articles">Einträge</el-menu-item>
                <el-menu-item index="/tags">Tags</el-menu-item>
                <el-menu-item index="/articles/add">Neuer Eintrag</el-menu-item>
                <el-menu-item v-if="loggedIn" index="/logout">Logout</el-menu-item>
                <el-menu-item v-if="!loggedIn" index="/login">Login</el-menu-item>
            </el-menu>
        </el-header>
        <el-container class="main-container">
            <el-main>
                <router-view/>
            </el-main>
        </el-container>
        <el-footer class="footer" height="240px">
        </el-footer>
    </el-container>
</template>

<script>
  import auth from '@/utils/auth'
  export default {
    data () {
      return {
        loggedIn: auth.loggedIn()
      }
    },
    created () {
      auth.onChange = loggedIn => {
        this.loggedIn = loggedIn
      }
    }
  }
</script>

<style>
    .main-container {
        max-width: 1170px;
    }
    .el-menu {
        background-color: transparent;
        margin-top:80px;
    }
    .el-menu li {
        background-color: rgba(255, 255, 255, .8);
    }
    .el-menu--horizontal {
        border-bottom: none;
    }
    .el-header {
        background-color: #f5f5f6;
        height: 165px;
        margin-bottom: 30px;
        background-image: url(https://www.team.jobs/Img/header_enterprises.jpg)
    }
    .el-menu-dem {
        background-color: #f5f5f6;
    }
    .footer {
        background-color: #F6F7F9;
    }

</style>
