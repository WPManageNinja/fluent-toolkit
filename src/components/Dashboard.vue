<template>
    <div class="fbeta_dashboard">
        <div class="fluent_header">
            <h1 style="margin-bottom: 10px;">
                Fluent Plugins Dashboard
                <el-tag type="success">{{appVars.version}}</el-tag>
                <el-button :loading="installing" :disabled="installing" @click="updateToolkit()" style="float: right;" type="danger" v-if="appVars.require_update">Update Toolkit</el-button>
            </h1>
            <p>Be the first to experince the upcoming features of FluentPlugins and manage all the fluent plugins from a
                single place.</p>
        </div>
        <div class="fluent_content">
            <h3>Plugins</h3>
            <el-skeleton v-if="loading" :animated="true" :rows="10"/>
            <div v-loading="installing" element-loading-text="Installing... Please wait...." class="fluent_plugins">
                <div v-for="plugin in betaPlugins" class="fluent_plugin">
                    <div class="fluent_plugin_name">
                        <img :src="plugin.logo" :alt="plugin.name"/>
                        <div class="fluent_plugin_description">
                            <h3>
                                {{ plugin.name }}
                                <el-tag type="info" v-if="!plugin.installed_version">{{ plugin.stable_version }}
                                </el-tag>
                            </h3>
                            <p>{{ plugin.sub_title }}</p>
                            <div class="fluent_plugin_statuses">
                                <el-tag v-if="plugin.installed_version">Installed:
                                    {{ plugin.installed_version }}
                                </el-tag>
                                <el-button @click="installPlugin(plugin, 'yes')" type="danger" size="small" plain
                                           v-if="plugin.has_beta_update">Install Beta - {{ plugin.beta_version }}
                                </el-button>
                                <span v-if="plugin.is_pro">
                                    License: {{ hideLicenseKey(plugin.license_key) || 'n/a' }}
                                    <el-button v-if="plugin.has_beta_update" @click="setCustomLicenseKey(plugin)"
                                               size="small" text>
                                        <el-icon>
                                            <EditPen/>
                                        </el-icon>
                                    </el-button>
                                </span>
                                <a target="_blank" rel="noopener" v-if="plugin.changelog_url"
                                   :href="plugin.changelog_url">Change log</a>
                            </div>
                        </div>
                    </div>
                    <div class="fluent_plugin_actions">
                        <el-button v-if="!plugin.installed_version" @click="installPlugin(plugin)" type="primary">
                            Install
                        </el-button>
                        <el-button @click="installPlugin(plugin)" type="danger" v-else-if="plugin.has_update">Update to
                            {{ plugin.stable_version }}
                        </el-button>
                        <el-tag :readonly="true" v-else type="success">Up to date</el-tag>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import {EditPen} from "@element-plus/icons-vue";

export default {
    name: 'Dashboard',
    components: {EditPen},
    data() {
        return {
            betaPlugins: [],
            installing: false,
            loading: false,
        }
    },
    methods: {
        getBetaPlugins() {
            this.loading = true;
            this.$get('fluent_beta_get_beta_versions')
                .then(response => {
                    this.betaPlugins = response.beta_versions;
                })
                .catch(error => {
                    this.$handleError(error);
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        installPlugin(plugin, beta = '') {
            let licenseKey = plugin.license_key;
            if (plugin.require_license == 'yes' && !plugin.license_key) {
                licenseKey = prompt('Please enter your license key for ' + plugin.name + ' to install.');
                if (!licenseKey) {
                    this.$notify.error('Please enter a valid license key.');
                    return;
                }
            }

            this.installing = true;
            this.$post('fluent-beta-install', {
                slug: plugin.slug,
                license_key: licenseKey,
                beta: beta
            })
                .then(response => {
                    this.$notify.success(response.message);
                    // reload the page
                    if (response.message) {
                        window.location.reload();
                    }
                })
                .catch(error => {
                    this.$handleError(error);
                })
                .finally(() => {
                    this.installing = false;
                    this.getBetaPlugins();
                });
        },
        updateToolkit() {
            this.installing = true;
            this.installPlugin({
                slug: 'fluent-toolkit'
            });
        },
        hideLicenseKey(licenseKey) {
            if (!licenseKey) {
                return '';
            }

            return licenseKey.replace(/.(?=.{6})/g, '*').slice(-10);
        },
        setCustomLicenseKey(plugin) {
            let licenseKey = prompt('Please enter your license key for .' + plugin.name);
            if (!licenseKey) {
                this.$notify.error('Please enter a valid license key.');
                return;
            }
            plugin.license_key = licenseKey;
        }
    },
    mounted() {
        this.getBetaPlugins();
    },
    created() {
        jQuery('.update-nag,.notice, #wpbody-content > .updated, #wpbody-content > .error').remove();
    }
}
</script>
