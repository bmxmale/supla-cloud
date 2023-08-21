<template>
    <div>
        <label class="d-flex">
            <span class="flex-grow-1">{{ $t(labelI18n) }}</span>
            <span class="label-hint" v-if="!disabled"
                v-html="$t('type {code} to show available variables', {code: '<code>{</code>'})"></span>
            <a class="ml-2" @click="helpShown = !helpShown" v-if="!disabled">
                <fa icon="question-circle"/>
            </a>
        </label>
        <transition-expand>
            <div class="variable-field-help" v-if="helpShown">
                <div class="d-flex">
                    <h6 class="flex-grow-1">{{ $t('Available variables') }}</h6>
                    <a class="ml-2" @click="helpShown = !helpShown">
                        <fa icon="times-circle"/>
                    </a>
                </div>
                <ul>
                    <li v-for="item in allVariables" :key="item.value">
                        {{ item.label }} <code @click="insertVariable(item)" class="pointer">{{ '{' + item.value }}</code>
                    </li>
                </ul>
            </div>
        </transition-expand>
        <div class="variable-field-container">
            <div class="preview" v-html="modelHighlighted" ref="preview"></div>
            <Mentionable
                :keys="['{']"
                :items="allVariables"
                insert-space>
                <input type="text" class="form-control" v-model="model" name="notification-title" :disabled="disabled"
                    maxlength="100" autocomplete="off" @scroll="syncScroll()" ref="input">
                <template #no-result>
                    <div>{{ $t('No results') }}</div>
                </template>
                <template #item="{ item }">
                    <div class="variable-hint">
                        {{ item.label }} <code>{{ '{' + item.value }}</code>
                    </div>
                </template>
            </Mentionable>
        </div>
    </div>
</template>

<script>
    import {Mentionable} from 'vue-mention'
    import ChannelFunction from "@/common/enums/channel-function";
    import TransitionExpand from "@/common/gui/transition-expand.vue";

    export default {
        components: {TransitionExpand, Mentionable},
        props: {
            labelI18n: String,
            value: String,
            disabled: Boolean,
            subject: Object,
        },
        data() {
            return {
                helpShown: false,
            };
        },
        methods: {
            insertVariable(variable) {
                this.model = this.model + '{' + variable.value;
            },
            syncScroll() {
                this.$refs.preview.scrollLeft = this.$refs.input.scrollLeft;
            }
        },
        computed: {
            model: {
                get() {
                    return this.value;
                },
                set(value) {
                    this.$emit('input', value);
                }
            },
            modelHighlighted() {
                const highlighted = this.model
                    .replace(new RegExp("&", "g"), "&amp;")
                    .replace(new RegExp("<", "g"), "&lt;");
                return this.allVariables
                    .reduce((h, {value}) => h.replace(new RegExp('{' + value, 'g'), `<span class="variable">{${value}</span>`), highlighted);
            },
            subjectVariables() {
                switch (this.subject?.functionId) {
                    case ChannelFunction.THERMOMETER:
                        return [
                            {label: this.$t('Temperature'), value: '{temperature}'},
                        ];
                    case ChannelFunction.HUMIDITY:
                        return [
                            {label: this.$t('Humidity'), value: '{humidity}'},
                        ];
                    case ChannelFunction.HUMIDITYANDTEMPERATURE:
                        return [
                            {label: this.$t('Temperature'), value: '{temperature}'},
                            {label: this.$t('Humidity'), value: '{humidity}'},
                        ];
                    case ChannelFunction.ELECTRICITYMETER: {
                        const enabledPhases = this.subject.config.enabledPhases || [1, 2, 3];
                        const defaultModes = ['forwardActiveEnergy', 'reverseActiveEnergy', 'forwardReactiveEnergy', 'reverseReactiveEnergy'];
                        const availableModes = this.subject.config.countersAvailable || defaultModes;
                        const variables = [];
                        variables.push({label: this.$t('Average voltage'), value: '{voltage_avg}'});
                        enabledPhases.forEach(phaseNo => variables.push({
                            label: `${this.$t('Voltage')} (${this.$t(`Phase ${phaseNo}`).toLowerCase()})`,
                            value: `{voltage${phaseNo}}`
                        }));
                        variables.push({label: this.$t('Current'), value: '{current_sum}'});
                        enabledPhases.forEach(phaseNo => variables.push({
                            label: `${this.$t('Current')} (${this.$t(`Phase ${phaseNo}`).toLowerCase()})`,
                            value: `{current${phaseNo}}`
                        }));
                        variables.push({label: this.$t('Power active'), value: '{power_active_sum}'});
                        enabledPhases.forEach(phaseNo => variables.push({
                            label: `${this.$t('Power active')} (${this.$t(`Phase ${phaseNo}`).toLowerCase()})`,
                            value: `{power_active${phaseNo}}`
                        }));
                        variables.push({label: this.$t('Power reactive'), value: '{power_reactive_sum}'});
                        enabledPhases.forEach(phaseNo => variables.push({
                            label: `${this.$t('Power reactive')} (${this.$t(`Phase ${phaseNo}`).toLowerCase()})`,
                            value: `{power_reactive${phaseNo}}`
                        }));
                        variables.push({label: this.$t('Power apparent'), value: '{power_apparent_sum}'});
                        enabledPhases.forEach(phaseNo => variables.push({
                            label: `${this.$t('Power apparent')} (${this.$t(`Phase ${phaseNo}`).toLowerCase()})`,
                            value: `{power_apparent${phaseNo}}`
                        }));
                        if (availableModes.includes('forwardActiveEnergy')) {
                            variables.push({label: this.$t('Forward active energy'), value: '{fae_sum}'});
                            if (availableModes.includes('forwardActiveEnergyBalanced')) {
                                variables.push({label: this.$t('Forward active energy balanced'), value: '{fae_balanced}'});
                            }
                            enabledPhases.forEach(phaseNo => variables.push({
                                label: `${this.$t('Forward active energy')} (${this.$t(`Phase ${phaseNo}`).toLowerCase()})`,
                                value: `{fae${phaseNo}}`
                            }));
                        }
                        if (availableModes.includes('reverseActiveEnergy')) {
                            variables.push({label: this.$t('Reverse active energy'), value: '{rae_sum}'});
                            if (availableModes.includes('reverseActiveEnergyBalanced')) {
                                variables.push({label: this.$t('Reverse active energy balanced'), value: '{rae_balanced}'});
                            }
                            enabledPhases.forEach(phaseNo => variables.push({
                                label: `${this.$t('Reverse active energy')} (${this.$t(`Phase ${phaseNo}`).toLowerCase()})`,
                                value: `{rae${phaseNo}}`
                            }));
                        }
                        return variables;
                    }
                    default:
                        return [];
                }
            },
            allVariables() {
                return [
                    ...this.subjectVariables,
                    {label: this.$t('Date'), value: '{date}'},
                    {label: this.$t('Time'), value: '{time}'},
                    {label: this.$t('Date and time'), value: '{date_time}'},
                ].map(v => ({label: v.label, value: v.value.substring(1), searchText: `${v.label} ${v.value}`}))
            }
        },
    };
</script>

<style lang="scss">
    @import '../styles/variables';

    .mention-item {
        padding: 4px 10px;
        border-radius: 4px;
    }

    .mention-selected {
        background: $supla-green;
        color: white;
    }

    .label-hint {
        font-weight: normal;
        font-size: .8em;
        color: $supla-grey-dark;
    }

    .variable-field-container {
        position: relative;
        .form-control {
            color: transparent;
            background: transparent;
            caret-color: black;
        }
        .preview {
            position: absolute;
            top: 0;
            left: 0;
            padding: 8px 23px 0 13px;
            overflow: hidden;
            white-space: nowrap;
            width: 100%;
            .variable {
                color: #3f903f;
                text-shadow: -0.5px 0 #3f903f;
            }
        }
    }

    .variable-field-help {
        font-size: .8em;
    }
</style>