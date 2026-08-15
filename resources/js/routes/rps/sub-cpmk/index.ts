import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\ObeWorkspaceController::store
 * @see app/Http/Controllers/ObeWorkspaceController.php:193
 * @route '/rps/{rps}/sub-cpmk'
 */
export const store = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/rps/{rps}/sub-cpmk',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::store
 * @see app/Http/Controllers/ObeWorkspaceController.php:193
 * @route '/rps/{rps}/sub-cpmk'
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
 * @see app/Http/Controllers/ObeWorkspaceController.php:193
 * @route '/rps/{rps}/sub-cpmk'
 */
store.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::store
 * @see app/Http/Controllers/ObeWorkspaceController.php:193
 * @route '/rps/{rps}/sub-cpmk'
 */
    const storeForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::store
 * @see app/Http/Controllers/ObeWorkspaceController.php:193
 * @route '/rps/{rps}/sub-cpmk'
 */
        storeForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:242
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
export const update = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/rps/{rps}/sub-cpmk/{subCpmk}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:242
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
update.url = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    subCpmk: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                subCpmk: args.subCpmk,
                }

    return update.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{subCpmk}', parsedArgs.subCpmk.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:242
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
update.put = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:242
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
    const updateForm = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
 * @see app/Http/Controllers/ObeWorkspaceController.php:242
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
        updateForm.put = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\ObeWorkspaceController::destroy
 * @see app/Http/Controllers/ObeWorkspaceController.php:299
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
export const destroy = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/rps/{rps}/sub-cpmk/{subCpmk}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::destroy
 * @see app/Http/Controllers/ObeWorkspaceController.php:299
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
destroy.url = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    subCpmk: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                subCpmk: args.subCpmk,
                }

    return destroy.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{subCpmk}', parsedArgs.subCpmk.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::destroy
 * @see app/Http/Controllers/ObeWorkspaceController.php:299
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
destroy.delete = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::destroy
 * @see app/Http/Controllers/ObeWorkspaceController.php:299
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
    const destroyForm = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
 * @see app/Http/Controllers/ObeWorkspaceController.php:299
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
        destroyForm.delete = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const subCpmk = {
    store: Object.assign(store, store),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
}

export default subCpmk