<template>
    <div
        v-if="!_.isEmpty($page.custom_fields)">
        <template
            v-for="(field, key) in $page.custom_fields">
            <template
                v-if="!_.isEmpty(ticket[key])">
                <span class="ml-2"
                    v-if="_.includes(['date'], field.type)"
                    :title="field.label">
                    <span class="badge badge-secondary">
                        <i class="far fa-calendar-check"></i>
                        {{ticket[key]}}
                    </span>
                </span>
                <span class="ml-2"
                    v-if="_.includes(['datetime-local'], field.type)"
                    :title="field.label">
                    <span class="badge badge-secondary">
                        <i class="far fa-calendar-check"></i>
                        {{$commonFunction.formatDateTime(ticket[key])}}
                    </span>
                </span>
                <span class="ml-2"
                    v-if="_.includes(['email'], field.type)"
                    :title="field.label">
                    <span class="badge badge-secondary">
                        <i class="far fa-envelope"></i>
                        <a :href="`mailto:${ticket[key]}`" target="_blank"
                            class="text-white">
                            {{ticket[key]}}
                        </a>
                    </span>
                </span>
                <span class="ml-2"
                    v-if="_.includes(['number'], field.type)"
                    :title="field.label">
                    <span class="badge badge-secondary">
                        {{ticket[key]}}
                    </span>
                </span>
                <p class="mb-1"
                    v-if="_.includes(['url'], field.type)"
                    :title="field.label">
                    <a :href="ticket[key]" target="_blank">
                        <i class="fas fa-external-link-alt fa-sm"></i>
                        <small>{{ticket[key]}}</small>
                    </a>
                </p>
                <p class="mb-1"
                    v-if="_.includes(['text'], field.type)"
                    :title="field.label">
                    <small>{{ticket[key]}}</small>
                </p>
                <p class="mb-1"
                    v-if="_.includes(['textarea'], field.type)"
                    :title="field.label">
                    <small>{{ticket[key]}}</small>
                </p>
            </template>
        </template>
    </div>
</template>
<script>
export default {
    props:[
        'ticket'
    ]
}
</script>