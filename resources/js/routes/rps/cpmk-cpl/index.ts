import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:130
 * @route '/rps/{rps}/cpmk-cpl'
 */
export const update = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/rps/{rps}/cpmk-cpl',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:130
 * @route '/rps/{rps}/cpmk-cpl'
 */
update.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:130
 * @route '/rps/{rps}/cpmk-cpl'
 */
update.put = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:130
 * @route '/rps/{rps}/cpmk-cpl'
 */
    const updateForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
 * @see app/Http/Controllers/ObeWorkspaceController.php:130
 * @route '/rps/{rps}/cpmk-cpl'
 */
        updateForm.put = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
const cpmkCpl = {
    update: Object.assign(update, update),
}

export default cpmkCpl