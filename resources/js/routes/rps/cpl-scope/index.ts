import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\RpsCplScopeController::store
 * @see app/Http/Controllers/RpsCplScopeController.php:13
 * @route '/rps/{rps}/cpl-scope'
 */
export const store = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/rps/{rps}/cpl-scope',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsCplScopeController::store
 * @see app/Http/Controllers/RpsCplScopeController.php:13
 * @route '/rps/{rps}/cpl-scope'
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
* @see \App\Http\Controllers\RpsCplScopeController::store
 * @see app/Http/Controllers/RpsCplScopeController.php:13
 * @route '/rps/{rps}/cpl-scope'
 */
store.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsCplScopeController::store
 * @see app/Http/Controllers/RpsCplScopeController.php:13
 * @route '/rps/{rps}/cpl-scope'
 */
    const storeForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsCplScopeController::store
 * @see app/Http/Controllers/RpsCplScopeController.php:13
 * @route '/rps/{rps}/cpl-scope'
 */
        storeForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\RpsCplScopeController::destroy
 * @see app/Http/Controllers/RpsCplScopeController.php:72
 * @route '/rps/{rps}/cpl-scope/{cpl}'
 */
export const destroy = (args: { rps: string | number, cpl: string | number } | [rps: string | number, cpl: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/rps/{rps}/cpl-scope/{cpl}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\RpsCplScopeController::destroy
 * @see app/Http/Controllers/RpsCplScopeController.php:72
 * @route '/rps/{rps}/cpl-scope/{cpl}'
 */
destroy.url = (args: { rps: string | number, cpl: string | number } | [rps: string | number, cpl: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    cpl: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                cpl: args.cpl,
                }

    return destroy.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{cpl}', parsedArgs.cpl.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsCplScopeController::destroy
 * @see app/Http/Controllers/RpsCplScopeController.php:72
 * @route '/rps/{rps}/cpl-scope/{cpl}'
 */
destroy.delete = (args: { rps: string | number, cpl: string | number } | [rps: string | number, cpl: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\RpsCplScopeController::destroy
 * @see app/Http/Controllers/RpsCplScopeController.php:72
 * @route '/rps/{rps}/cpl-scope/{cpl}'
 */
    const destroyForm = (args: { rps: string | number, cpl: string | number } | [rps: string | number, cpl: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsCplScopeController::destroy
 * @see app/Http/Controllers/RpsCplScopeController.php:72
 * @route '/rps/{rps}/cpl-scope/{cpl}'
 */
        destroyForm.delete = (args: { rps: string | number, cpl: string | number } | [rps: string | number, cpl: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const cplScope = {
    store: Object.assign(store, store),
destroy: Object.assign(destroy, destroy),
}

export default cplScope