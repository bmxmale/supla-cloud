<template>
    <div>
        <form @submit.stop.prevent="submitProgramsEdit()" v-if="editingPrograms">
            <div class="thermostat-program-buttons editing">
                <div v-for="program in editingPrograms" :key="program.programNo"
                    :class="[ `text-center mx-3 thermostat-program-button thermostat-program-button-${program.programNo}`]">
                    <div>{{ $t('Program {programNo}', {programNo: program.programNo}) }}</div>
                    <div class="form-group form-group-sm my-1" v-if="program.settings.mode === 'HEAT' || autoModeAvailable">
                        <span class="input-group">
                            <span class="input-group-addon">
                                <IconHeating/>
                            </span>
                            <input type="number"
                                step="0.1"
                                min="-10"
                                max="150"
                                class="form-control text-center"
                                v-model="program.settings.setpointTemperatureHeat">
                            <span class="input-group-addon">
                                &deg;C
                            </span>
                        </span>
                    </div>
                    <div class="form-group form-group-sm my-1" v-if="program.settings.mode === 'COOL' || autoModeAvailable">
                        <span class="input-group">
                            <span class="input-group-addon">
                                <IconCooling/>
                            </span>
                            <input type="number"
                                step="0.1"
                                min="-10"
                                max="150"
                                class="form-control text-center"
                                v-model="program.settings.setpointTemperatureCool">
                            <span class="input-group-addon">
                                &deg;C
                            </span>
                        </span>
                    </div>
                </div>
            </div>
            <transition-expand>
                <div v-if="invalidProgramErrorText" class="mx-3 mt-2 text-danger">
                    {{ invalidProgramErrorText }}
                </div>
            </transition-expand>
            <div class="mx-3 mt-2 mb-4">
                <button type="submit" class="btn btn-success mr-3">
                    <fa icon="check" fixed-width/>
                    {{ $t('OK') }}
                </button>
                <button type="button" class="btn btn-default" @click="cancelProgramsEdit()">
                    <fa icon="cancel" fixed-width/>
                    {{ $t('Cancel') }}
                </button>
            </div>
        </form>
        <div v-else>
            <div class="thermostat-program-buttons">
                <div v-for="program in programs" :key="program.programNo"
                    @click="chooseForSelection(program.programNo)"
                    :class="[
                `text-center mx-3 thermostat-program-button thermostat-program-button-${program.programNo}`,
                {chosen: program.chosen}
                ]">
                    <div v-if="program.programNo > 0">
                        <div>{{ $t('Program {programNo}', {programNo: program.programNo}) }}</div>
                        <div>
                            <span v-if="program.settings.mode === 'HEAT'">
                                <IconHeating/>
                                {{ program.settings.setpointTemperatureHeat }}&deg;C
                            </span>
                            <span v-else-if="program.settings.mode === 'COOL'">
                                <IconCooling/>
                                {{ program.settings.setpointTemperatureCool }}&deg;C
                            </span>
                            <span v-else>
                                <IconHeating/>
                                {{ program.settings.setpointTemperatureHeat }}&deg;C
                                &hyphen;
                                <IconCooling/>
                                {{ program.settings.setpointTemperatureCool }}&deg;C
                            </span>
                        </div>
                    </div>
                    <div v-else>
                        <fa icon="power-off" fixed-width/>
                        {{ $t('Turn off') }}
                    </div>
                </div>
            </div>
            <div class="mt-2 mb-4">
                <button type="button" class="btn btn-link" @click="editPrograms()">
                    <fa icon="edit" fixed-width/>
                    {{ $t('Edit program settings') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
    import {mapValues} from "lodash";
    import {deepCopy} from "@/common/utils";
    import IconHeating from "@/common/icons/icon-heating.vue";
    import IconCooling from "@/common/icons/icon-cooling.vue";
    import ChannelFunction from "@/common/enums/channel-function";
    import TransitionExpand from "@/common/gui/transition-expand.vue";

    export default {
        components: {TransitionExpand, IconCooling, IconHeating},
        props: {
            subject: Object,
            value: Object,
        },
        data() {
            return {
                programs: undefined,
                editingPrograms: undefined,
                invalidProgramErrorText: '',
            }
        },
        beforeMount() {
            this.initFromValue();
        },
        methods: {
            initFromValue() {
                this.programs = Object.values(mapValues(this.value, (settings, programNo) => {
                    return {programNo: +programNo, settings: deepCopy(settings), chosen: false};
                }));
                this.programs.push({programNo: 0, chosen: false})
            },
            chooseForSelection(programNo) {
                if (this.editing) {
                    return;
                }
                this.programs.forEach(program => {
                    program.chosen = program.programNo === programNo;
                });
                this.$emit('programChosen', programNo >= 0 ? programNo : false);
            },
            editPrograms() {
                this.chooseForSelection(-1);
                this.editingPrograms = deepCopy(this.programs);
                this.editingPrograms.pop();
            },
            cancelProgramsEdit() {
                this.editingPrograms = undefined;
            },
            submitProgramsEdit() {
                this.invalidProgramErrorText = '';
                const newPrograms = {};
                this.editingPrograms.forEach(({programNo, settings}) => {
                    const hasMin = settings.setpointTemperatureHeat !== null && settings.setpointTemperatureHeat !== '';
                    const hasMax = settings.setpointTemperatureCool !== null && settings.setpointTemperatureCool !== '';
                    if (!hasMin && !hasMax) {
                        this.invalidProgramErrorText = this.$t('All programs must define at least one temperature threshold.');
                    }
                    if (hasMin && hasMax && +settings.setpointTemperatureHeat >= +settings.setpointTemperatureCool) {
                        this.invalidProgramErrorText = this.$t('When the program defines both heating and cooling temperatures, the first must be lower than the second.');
                    }
                    const mode = this.autoModeAvailable ? (hasMin ? (hasMax ? 'AUTO' : 'HEAT') : 'COOL') : settings.mode;
                    newPrograms[programNo] = {
                        mode,
                        setpointTemperatureHeat: hasMin ? +settings.setpointTemperatureHeat : null,
                        setpointTemperatureCool: hasMax ? +settings.setpointTemperatureCool : null,
                    };
                });
                if (!this.invalidProgramErrorText) {
                    this.$emit('input', newPrograms);
                }
            },
        },
        computed: {
            autoModeAvailable() {
                return this.subject.functionId === ChannelFunction.HVAC_THERMOSTAT_AUTO;
            },
        },
        watch: {
            value() {
                this.editingPrograms = undefined;
                this.initFromValue();
            }
        }
    };
</script>

<style lang="scss">
    @import '../../styles/variables';
    @import '../../styles/mixins';

    .thermostat-program-buttons {
        display: flex;
        .thermostat-program-button {
            border-radius: 5px;
            padding: 5px 0;
            cursor: pointer;
            transition: background-color .2s linear;
            width: 20%;
            &.chosen {
                font-weight: bold;
            }
        }
        &.editing {
            .thermostat-program-button {
                cursor: default;
            }
        }
    }

</style>