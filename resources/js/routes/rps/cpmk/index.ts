import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\ObeWorkspaceController::store
 * @see app/Http/Controllers/ObeWorkspaceController.php:16
 * @route '/rps/{rps}/cpmk'
 */
export const store = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/rps/{rps}/cpmk',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::store
 * @see app/Http/Controllers/ObeWorkspaceController.php:16
 * @route '/rps/{rps}/cpmk'
 */
store.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::store
 * @see app/Http/Controllers/ObeWorkspaceController.php:16
 * @route '/rps/{rps}/cpmk'
 */
store.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::store
 * @see app/Http/Controllers/ObeWorkspaceController.php:16
 * @route '/rps/{rps}/cpmk'
 */
    const storeForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::store
 * @see app/Http/Controllers/ObeWorkspaceController.php:16
 * @route '/rps/{rps}/cpmk'
 */
        storeForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:45
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
export const update = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/rps/{rps}/cpmk/{cpmk}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:45
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
update.url = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    cpmk: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                cpmk: args.cpmk,
                }

    return update.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{cpmk}', parsedArgs.cpmk.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:45
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
update.put = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:45
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
    const updateForm = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:45
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
        updateForm.put = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::reset
 * @see app/Http/Controllers/ObeWorkspaceController.php:71
 * @route '/rps/{rps}/cpmk/{cpmk}/reset'
 */
export const reset = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reset.url(args, options),
    method: 'post',
})

reset.definition = {
    methods: ["post"],
    url: '/rps/{rps}/cpmk/{cpmk}/reset',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::reset
 * @see app/Http/Controllers/ObeWorkspaceController.php:71
 * @route '/rps/{rps}/cpmk/{cpmk}/reset'
 */
reset.url = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    cpmk: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                cpmk: args.cpmk,
                }

    return reset.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{cpmk}', parsedArgs.cpmk.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::reset
 * @see app/Http/Controllers/ObeWorkspaceController.php:71
 * @route '/rps/{rps}/cpmk/{cpmk}/reset'
 */
reset.post = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reset.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::reset
 * @see app/Http/Controllers/ObeWorkspaceController.php:71
 * @route '/rps/{rps}/cpmk/{cpmk}/reset'
 */
    const resetForm = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reset.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::reset
 * @see app/Http/Controllers/ObeWorkspaceController.php:71
 * @route '/rps/{rps}/cpmk/{cpmk}/reset'
 */
        resetForm.post = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reset.url(args, options),
            method: 'post',
        })
    
    reset.form = resetForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::destroy
 * @see app/Http/Controllers/ObeWorkspaceController.php:104
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
export const destroy = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/rps/{rps}/cpmk/{cpmk}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::destroy
 * @see app/Http/Controllers/ObeWorkspaceController.php:104
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
destroy.url = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    cpmk: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                cpmk: args.cpmk,
                }

    return destroy.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{cpmk}', parsedArgs.cpmk.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::destroy
 * @see app/Http/Controllers/ObeWorkspaceController.php:104
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
destroy.delete = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::destroy
 * @see app/Http/Controllers/ObeWorkspaceController.php:104
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
    const destroyForm = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::destroy
 * @see app/Http/Controllers/ObeWorkspaceController.php:104
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
        destroyForm.delete = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const cpmk = {
    store: Object.assign(store, store),
update: Object.assign(update, update),
reset: Object.assign(reset, reset),
destroy: Object.assign(destroy, destroy),
}

export default cpmk