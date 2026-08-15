import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
import documentMeta from './document-meta'
import simulation from './simulation'
import weeks from './weeks'
import cpmk from './cpmk'
import cpmkCpl from './cpmk-cpl'
import cplScope from './cpl-scope'
import subCpmk from './sub-cpmk'
import materials from './materials'
import ai from './ai'
import assessments from './assessments'
import tasks from './tasks'
/**
* @see \App\Http\Controllers\RpsController::index
 * @see app/Http/Controllers/RpsController.php:18
 * @route '/rps'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/rps',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\RpsController::index
 * @see app/Http/Controllers/RpsController.php:18
 * @route '/rps'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsController::index
 * @see app/Http/Controllers/RpsController.php:18
 * @route '/rps'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\RpsController::index
 * @see app/Http/Controllers/RpsController.php:18
 * @route '/rps'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\RpsController::index
 * @see app/Http/Controllers/RpsController.php:18
 * @route '/rps'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\RpsController::index
 * @see app/Http/Controllers/RpsController.php:18
 * @route '/rps'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\RpsController::index
 * @see app/Http/Controllers/RpsController.php:18
 * @route '/rps'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\RpsController::create
 * @see app/Http/Controllers/RpsController.php:40
 * @route '/rps/baru'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/rps/baru',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\RpsController::create
 * @see app/Http/Controllers/RpsController.php:40
 * @route '/rps/baru'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsController::create
 * @see app/Http/Controllers/RpsController.php:40
 * @route '/rps/baru'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\RpsController::create
 * @see app/Http/Controllers/RpsController.php:40
 * @route '/rps/baru'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\RpsController::create
 * @see app/Http/Controllers/RpsController.php:40
 * @route '/rps/baru'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\RpsController::create
 * @see app/Http/Controllers/RpsController.php:40
 * @route '/rps/baru'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\RpsController::create
 * @see app/Http/Controllers/RpsController.php:40
 * @route '/rps/baru'
 */
        createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    create.form = createForm
/**
* @see \App\Http\Controllers\RpsController::store
 * @see app/Http/Controllers/RpsController.php:89
 * @route '/rps'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/rps',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsController::store
 * @see app/Http/Controllers/RpsController.php:89
 * @route '/rps'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsController::store
 * @see app/Http/Controllers/RpsController.php:89
 * @route '/rps'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsController::store
 * @see app/Http/Controllers/RpsController.php:89
 * @route '/rps'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsController::store
 * @see app/Http/Controllers/RpsController.php:89
 * @route '/rps'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\RpsController::show
 * @see app/Http/Controllers/RpsController.php:111
 * @route '/rps/{rps}'
 */
export const show = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/rps/{rps}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\RpsController::show
 * @see app/Http/Controllers/RpsController.php:111
 * @route '/rps/{rps}'
 */
show.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rps: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                }

    return show.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsController::show
 * @see app/Http/Controllers/RpsController.php:111
 * @route '/rps/{rps}'
 */
show.get = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\RpsController::show
 * @see app/Http/Controllers/RpsController.php:111
 * @route '/rps/{rps}'
 */
show.head = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\RpsController::show
 * @see app/Http/Controllers/RpsController.php:111
 * @route '/rps/{rps}'
 */
    const showForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\RpsController::show
 * @see app/Http/Controllers/RpsController.php:111
 * @route '/rps/{rps}'
 */
        showForm.get = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\RpsController::show
 * @see app/Http/Controllers/RpsController.php:111
 * @route '/rps/{rps}'
 */
        showForm.head = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \App\Http\Controllers\RpsDeleteController::destroy
 * @see app/Http/Controllers/RpsDeleteController.php:12
 * @route '/rps/{rps}'
 */
export const destroy = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/rps/{rps}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\RpsDeleteController::destroy
 * @see app/Http/Controllers/RpsDeleteController.php:12
 * @route '/rps/{rps}'
 */
destroy.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rps: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                }

    return destroy.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsDeleteController::destroy
 * @see app/Http/Controllers/RpsDeleteController.php:12
 * @route '/rps/{rps}'
 */
destroy.delete = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\RpsDeleteController::destroy
 * @see app/Http/Controllers/RpsDeleteController.php:12
 * @route '/rps/{rps}'
 */
    const destroyForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsDeleteController::destroy
 * @see app/Http/Controllers/RpsDeleteController.php:12
 * @route '/rps/{rps}'
 */
        destroyForm.delete = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
/**
* @see \App\Http\Controllers\RpsAutomationController::smartDraft
 * @see app/Http/Controllers/RpsAutomationController.php:14
 * @route '/rps/{rps}/smart-draft'
 */
export const smartDraft = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: smartDraft.url(args, options),
    method: 'post',
})

smartDraft.definition = {
    methods: ["post"],
    url: '/rps/{rps}/smart-draft',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsAutomationController::smartDraft
 * @see app/Http/Controllers/RpsAutomationController.php:14
 * @route '/rps/{rps}/smart-draft'
 */
smartDraft.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rps: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                }

    return smartDraft.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsAutomationController::smartDraft
 * @see app/Http/Controllers/RpsAutomationController.php:14
 * @route '/rps/{rps}/smart-draft'
 */
smartDraft.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: smartDraft.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsAutomationController::smartDraft
 * @see app/Http/Controllers/RpsAutomationController.php:14
 * @route '/rps/{rps}/smart-draft'
 */
    const smartDraftForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: smartDraft.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsAutomationController::smartDraft
 * @see app/Http/Controllers/RpsAutomationController.php:14
 * @route '/rps/{rps}/smart-draft'
 */
        smartDraftForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: smartDraft.url(args, options),
            method: 'post',
        })
    
    smartDraft.form = smartDraftForm
/**
* @see \App\Http\Controllers\RpsAutomationController::validateObe
 * @see app/Http/Controllers/RpsAutomationController.php:73
 * @route '/rps/{rps}/validate-obe'
 */
export const validateObe = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: validateObe.url(args, options),
    method: 'post',
})

validateObe.definition = {
    methods: ["post"],
    url: '/rps/{rps}/validate-obe',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsAutomationController::validateObe
 * @see app/Http/Controllers/RpsAutomationController.php:73
 * @route '/rps/{rps}/validate-obe'
 */
validateObe.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rps: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                }

    return validateObe.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsAutomationController::validateObe
 * @see app/Http/Controllers/RpsAutomationController.php:73
 * @route '/rps/{rps}/validate-obe'
 */
validateObe.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: validateObe.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsAutomationController::validateObe
 * @see app/Http/Controllers/RpsAutomationController.php:73
 * @route '/rps/{rps}/validate-obe'
 */
    const validateObeForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: validateObe.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsAutomationController::validateObe
 * @see app/Http/Controllers/RpsAutomationController.php:73
 * @route '/rps/{rps}/validate-obe'
 */
        validateObeForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: validateObe.url(args, options),
            method: 'post',
        })
    
    validateObe.form = validateObeForm
const rps = {
    index: Object.assign(index, index),
create: Object.assign(create, create),
store: Object.assign(store, store),
show: Object.assign(show, show),
destroy: Object.assign(destroy, destroy),
documentMeta: Object.assign(documentMeta, documentMeta),
simulation: Object.assign(simulation, simulation),
weeks: Object.assign(weeks, weeks),
cpmk: Object.assign(cpmk, cpmk),
cpmkCpl: Object.assign(cpmkCpl, cpmkCpl),
cplScope: Object.assign(cplScope, cplScope),
subCpmk: Object.assign(subCpmk, subCpmk),
materials: Object.assign(materials, materials),
ai: Object.assign(ai, ai),
smartDraft: Object.assign(smartDraft, smartDraft),
validateObe: Object.assign(validateObe, validateObe),
assessments: Object.assign(assessments, assessments),
tasks: Object.assign(tasks, tasks),
}

export default rps