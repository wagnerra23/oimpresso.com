<template>
    <div>
        <!-- [ navigation menu ] start -->
        <nav class="pcoded-navbar">
            <div class="navbar-wrapper">
                <div class="navbar-brand header-logo">
                    <a href="#" class="b-brand">
                        <div class="b-bg">
                            <i class="fas fa-hands-helping"></i>
                        </div>
                        <span class="b-title">
                            {{APP_NAME}}
                        </span>
                    </a>
                    <a class="mobile-menu" id="mobile-collapse" href="#!">
                        <span></span>
                    </a>
                </div>
                <slot name="leftnav"></slot>
            </div>
        </nav>

        <!-- [ Header ] start -->
        <header class="navbar pcoded-header navbar-expand-lg navbar-light">
            <div class="m-header">
                <a class="mobile-menu" id="mobile-collapse1" href="#!">
                    <span></span>
                </a>
                <a href="#" class="b-brand">
                    <div class="b-bg">
                        <i class="fas fa-hands-helping"></i>
                    </div>
                    <span class="b-title">
                        {{APP_NAME}}
                    </span>
                </a>
            </div>
            <a class="mobile-menu" id="mobile-header" href="#!">
                <i class="feather icon-more-horizontal"></i>
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav mr-auto">
                    <li>
                        <a href="#" class="full-screen" onclick="javascript:toggleFullScreen()">
                            <i class="fas fa-expand fa-lg"></i>
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav ml-auto">
                    <li>
                        <button type="button" class="btn btn-secondary btn-sm">
                            {{__('messages.support_time')}}:
                            {{$page.support_current_datetime}}
                        </button>
                    </li>
                    <li>
                        <button type="button" class="btn btn-info btn-sm">
                            {{__('messages.your_time')}}:
                            {{userDatetime}}
                        </button>
                    </li>
                    <li>
                        <Notification></Notification>
                    </li>
                    <li>
                        <inertia-link :href="route_ziggy('logout')" method="post" :title="__('auth.logout')">
                            <i class="fas fa-sign-out-alt fa-lg"></i>
                        </inertia-link>
                    </li>   
                </ul>
            </div>
        </header>
        <!-- [ Header ] end -->

        <!-- [ chat user list ] start -->
        <!-- <section class="header-user-list"></section> -->
        <!-- [ chat user list ] end -->

        <!-- [ chat message ] start -->
        <!-- <section class="header-chat"></section> -->
        <!-- [ chat message ] end -->

        <!-- [ Main Content ] start -->
        <div class="pcoded-main-container">
            <div class="pcoded-wrapper">
                <div class="pcoded-content">
                    <div class="pcoded-inner-content">
                        <div class="main-body">
                            <div class="page-wrapper">
                                <!-- [ Main Content ] start -->
                                <!-- <div class="row">
                                    <div class="col-md-12">
                                        <alert :content="$page.flash.error" type="danger" :is_dismissible="true"></alert>
                                        <alert :content="$page.flash.success" type="success" :is_dismissible="true"></alert>
                                    </div>
                                </div>                                 -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <slot />
                                    </div>
                                </div>
                                <!-- [ Main Content ] end -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <footer>     
                <div class="footer-bottom">
                    <div class="container">
                        <div class="row float-right">
                            <div class="col-lg-12 float-right">
                                <p class="copyright mb-0" v-html="APP_COPYRIGHT_MSG">
                                </p>
                            </div>
                        </div>
                    </div>
                </div>    
            </footer>
        </div>
    </div>
</template>
<script>
    import Notification from './Notification';
    export default {
        components: {
            Notification,
        },
        props: {
          title: String,
        },
        computed : {
            userDatetime : function () {
                return moment().format('DD/MM/YYYY hh:mm A');
            }
        },
        watch: {
            title: {
                immediate: true,
                handler(title) {
                  document.title = title
                },
            },
            '$page.flash.success': {
                immediate: true,
                handler($msg) {
                    if (!_.isEmpty($msg)) {
                        toastr.success($msg);
                    }
                }
            },
            '$page.flash.error': {
                immediate: true,
                handler($msg) {
                    if (!_.isEmpty($msg)) {
                        toastr.error($msg);
                    }
                }
            }
        },
        data: function(){
            return {
                APP_NAME: APP.APP_NAME,
                APP_COPYRIGHT_MSG: APP.COPYRIGHT_MSG
            }
        },
        mounted() {
            initCommonThemeCode();
        }
    }
</script>